<?php

namespace App\Http\Controllers;

use App\Models\StudentAccountActivation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStudentImportController extends Controller
{
    private const MAX_ROWS = 1000;
    private const BATCH_TTL_MINUTES = 60;
    private const ACTIVATION_TTL_DAYS = 7;

    public function index()
    {
        return view('admin.student-import');
    }

    public function template(): StreamedResponse
    {
        return $this->csvDownload(
            'template-import-siswa-orbitflow.csv',
            ['nis', 'name', 'email', 'class_name'],
            []
        );
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'csv_file.required' => 'Pilih file CSV yang akan diimpor.',
            'csv_file.mimes' => 'File harus berformat CSV.',
            'csv_file.max' => 'Ukuran file maksimal 2 MB.',
        ]);

        $this->pruneExpiredBatches();
        $rows = $this->parseCsv($validated['csv_file']->getRealPath());
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'csv_file' => 'Maksimal 1.000 baris siswa dapat diproses dalam satu impor. Bagi file menjadi beberapa bagian.',
            ]);
        }

        $batchId = (string) Str::uuid();
        $batch = [
            'id' => $batchId,
            'created_by' => auth()->id(),
            'created_at' => now()->timestamp,
            'rows' => $rows,
            'valid_count' => collect($rows)->where('is_valid', true)->count(),
            'invalid_count' => collect($rows)->where('is_valid', false)->count(),
        ];

        Storage::disk('local')->put(
            $this->batchPath($batchId),
            json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        return redirect()->route('admin.students.import.batch', ['batch' => $batchId]);
    }

    public function showPreview(string $batch)
    {
        $payload = $this->loadBatch($batch);
        $rows = collect($payload['rows']);

        return view('admin.student-import-preview', [
            'batchId' => $batch,
            'rows' => $rows->take(150),
            'totalRows' => $rows->count(),
            'validCount' => (int) $payload['valid_count'],
            'invalidCount' => (int) $payload['invalid_count'],
        ]);
    }

    public function downloadErrorReport(string $batch): StreamedResponse
    {
        $payload = $this->loadBatch($batch);
        $rows = collect($payload['rows'])
            ->where('is_valid', false)
            ->map(fn ($row) => [
                'row_number' => $row['row_number'],
                'nis' => $row['data']['nis'],
                'name' => $row['data']['name'],
                'email' => $row['data']['email'],
                'class_name' => $row['data']['class_name'],
                'errors' => implode(' | ', $row['errors']),
            ])
            ->values()
            ->all();

        return $this->csvDownload(
            'laporan-kesalahan-import-siswa-' . now()->format('Ymd-His') . '.csv',
            ['row_number', 'nis', 'name', 'email', 'class_name', 'errors'],
            $rows
        );
    }

    public function import(Request $request, string $batch)
    {
        $payload = $this->loadBatch($batch);
        $created = [];
        $skipped = (int) $payload['invalid_count'];
        $expiresAt = now()->addDays(self::ACTIVATION_TTL_DAYS);

        try {
            DB::transaction(function () use ($payload, &$created, &$skipped, $expiresAt) {
                foreach ($payload['rows'] as $row) {
                if (! ($row['is_valid'] ?? false)) {
                    continue;
                }

                $data = $row['data'];
                $alreadyExists = User::query()
                    ->whereRaw('LOWER(email) = ?', [$data['email']])
                    ->orWhereRaw('LOWER(nis) = ?', [mb_strtolower($data['nis'])])
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;
                    continue;
                }

                $plainToken = Str::random(64);
                $student = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'nis' => $data['nis'],
                    'class_name' => $data['class_name'],
                    // The random password is never disclosed. The student chooses
                    // their own password after presenting the activation link.
                    'password' => Str::random(80),
                    'role' => User::ROLE_SISWA,
                    'is_active' => false,
                ]);

                StudentAccountActivation::create([
                    'user_id' => $student->id,
                    'token_hash' => hash('sha256', $plainToken),
                    'expires_at' => $expiresAt,
                    'created_by' => auth()->id(),
                ]);

                    $created[] = [
                        'nis' => $student->nis,
                        'name' => $student->name,
                        'email' => $student->email,
                        'activation_link' => URL::route('student.activation.show', ['token' => $plainToken]),
                        'expires_at' => $expiresAt->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' WIB',
                    ];
                }
            });
        } catch (QueryException $exception) {
            $sqlState = $exception->errorInfo[0] ?? null;
            if (in_array($sqlState, ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'csv_file' => 'Data siswa berubah saat impor berlangsung. Tidak ada akun yang disimpan. Unggah ulang CSV untuk memperbarui pratinjau.',
                ]);
            }
            throw $exception;
        }

        Storage::disk('local')->delete($this->batchPath($batch));

        ActivityLogger::record(
            'imports',
            'students.imported',
            count($created) . ' akun siswa berhasil diimpor; ' . $skipped . ' baris dilewati.',
            null,
            null,
            ['created_count' => count($created), 'skipped_count' => $skipped, 'batch_id' => $batch]
        );

        // Session data can survive an authenticated account switch in the same
        // browser. Bind both the import summary and its one-time activation-link
        // export to the administrator who performed the import.
        $request->session()->put('student_import_result', [
            'created_by' => (int) $request->user()->getAuthIdentifier(),
            'created_count' => count($created),
            'skipped_count' => $skipped,
            'created_at' => now()->timestamp,
            'expires_at' => $expiresAt->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') . ' WIB',
        ]);

        if ($created !== []) {
            $request->session()->put('student_import_activation_export', [
                'created_by' => (int) $request->user()->getAuthIdentifier(),
                'created_at' => now()->timestamp,
                'rows' => $created,
            ]);
        } else {
            $request->session()->forget('student_import_activation_export');
        }

        return redirect()->route('admin.students.import.result');
    }

    public function cancel(string $batch)
    {
        $this->loadBatch($batch);
        Storage::disk('local')->delete($this->batchPath($batch));

        return redirect()->route('admin.students.import.index')->with('success', 'Pratinjau impor dibatalkan. Tidak ada akun yang dibuat.');
    }

    public function result(Request $request)
    {
        $currentUserId = (int) $request->user()->getAuthIdentifier();
        $result = $request->session()->get('student_import_result');
        if (
            ! is_array($result)
            || (int) ($result['created_by'] ?? 0) !== $currentUserId
            || (int) ($result['created_at'] ?? 0) < now()->subHour()->timestamp
        ) {
            $request->session()->forget(['student_import_result', 'student_import_activation_export']);

            return redirect()->route('admin.students.import.index');
        }

        $export = $request->session()->get('student_import_activation_export');
        if ($export && (int) ($export['created_by'] ?? 0) !== $currentUserId) {
            $request->session()->forget('student_import_activation_export');
            $export = null;
        }
        $canDownload = $export && (int) ($export['created_at'] ?? 0) >= now()->subMinutes(30)->timestamp;

        return view('admin.student-import-result', [
            'result' => $result,
            'canDownload' => (bool) $canDownload,
        ]);
    }

    public function downloadActivationLinks(Request $request): StreamedResponse
    {
        $export = $request->session()->pull('student_import_activation_export');
        $currentUserId = (int) $request->user()->getAuthIdentifier();
        if (
            ! is_array($export)
            || (int) ($export['created_by'] ?? 0) !== $currentUserId
            || (int) ($export['created_at'] ?? 0) < now()->subMinutes(30)->timestamp
        ) {
            throw ValidationException::withMessages([
                'activation_export' => 'File tautan aktivasi tidak tersedia atau sudah kedaluwarsa. Terbitkan ulang tautan dari direktori siswa.',
            ]);
        }

        ActivityLogger::record(
            'imports',
            'student.activation_links_downloaded',
            count($export['rows']) . ' tautan aktivasi siswa diunduh oleh administrator.',
            null,
            null,
            ['link_count' => count($export['rows'])]
        );

        return $this->csvDownload(
            'tautan-aktivasi-siswa-' . now()->format('Ymd-His') . '.csv',
            ['nis', 'name', 'email', 'activation_link', 'expires_at'],
            $export['rows']
        );
    }

    public function reissueActivationLink(User $student): StreamedResponse
    {
        abort_unless($student->isStudent(), 404);

        $newLink = DB::transaction(function () use ($student) {
            $lockedStudent = User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedStudent->isStudent(), 404);

            if ($lockedStudent->is_active || ! $lockedStudent->studentAccountActivations()->whereNull('used_at')->exists()) {
                throw ValidationException::withMessages([
                    'activation' => 'Tautan hanya dapat diterbitkan ulang untuk akun impor yang masih menunggu aktivasi.',
                ]);
            }

            $lockedStudent->studentAccountActivations()
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $token = Str::random(64);
            $expiresAt = now()->addDays(self::ACTIVATION_TTL_DAYS);

            StudentAccountActivation::create([
                'user_id' => $lockedStudent->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'created_by' => auth()->id(),
            ]);

            return [
                'nis' => $lockedStudent->nis,
                'name' => $lockedStudent->name,
                'email' => $lockedStudent->email,
                'activation_link' => URL::route('student.activation.show', ['token' => $token]),
                'expires_at' => $expiresAt->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' WIB',
            ];
        });

        ActivityLogger::record(
            'accounts',
            'student.activation_link_reissued',
            'Tautan aktivasi baru diterbitkan untuk siswa ' . $newLink['name'] . '.',
            $student,
            null,
            ['nis' => $newLink['nis'], 'email' => $newLink['email'], 'expires_at' => $newLink['expires_at']],
            null,
            $newLink['name'] . ' · NIS ' . $newLink['nis']
        );

        $safeNis = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $newLink['nis']) ?: 'siswa';

        return $this->csvDownload(
            'tautan-aktivasi-' . $safeNis . '-' . now()->format('Ymd-His') . '.csv',
            ['nis', 'name', 'email', 'activation_link', 'expires_at'],
            [$newLink]
        );
    }

    /** @return array<int, array{row_number:int, data:array<string,string>, is_valid:bool, errors:array<int,string>}> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['csv_file' => 'File CSV tidak dapat dibaca.']);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['csv_file' => 'File CSV kosong.']);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $header = fgetcsv($handle, null, $delimiter, '"', '\\');
            if (! is_array($header) || $header === []) {
                throw ValidationException::withMessages(['csv_file' => 'Baris judul CSV tidak ditemukan.']);
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
            if (count($header) !== count(array_unique($header))) {
                throw ValidationException::withMessages(['csv_file' => 'Nama kolom pada CSV tidak boleh duplikat.']);
            }
            $requiredHeaders = ['nis', 'name', 'email', 'class_name'];
            $missingHeaders = array_values(array_diff($requiredHeaders, $header));
            if ($missingHeaders !== []) {
                throw ValidationException::withMessages([
                    'csv_file' => 'Kolom wajib tidak lengkap. Gunakan template CSV OrbitFlow. Kolom yang dibutuhkan: nis, name, email, class_name.',
                ]);
            }

            $indexes = array_flip($header);
            $existingNis = User::query()->whereNotNull('nis')->pluck('nis')->map(fn ($v) => mb_strtolower((string) $v))->all();
            $existingEmails = User::query()->pluck('email')->map(fn ($v) => mb_strtolower((string) $v))->all();
            $seenNis = [];
            $seenEmails = [];
            $rows = [];
            $recordNumber = 1;

            while (($fields = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
                $recordNumber++;
                if ($fields === [null] || collect($fields)->every(fn ($value) => trim((string) $value) === '')) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'csv_file' => 'Maksimal 1.000 baris siswa dapat diproses dalam satu impor. Bagi file menjadi beberapa bagian.',
                    ]);
                }

                $valueAt = fn (string $key): string => trim((string) ($fields[$indexes[$key]] ?? ''));
                $data = [
                    'nis' => $valueAt('nis'),
                    'name' => $valueAt('name'),
                    'email' => mb_strtolower($valueAt('email')),
                    'class_name' => $valueAt('class_name'),
                ];

                $validator = Validator::make($data, [
                    'nis' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'class_name' => ['required', 'string', 'max:50'],
                ]);
                $errors = [];
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = $message;
                }

                $nisKey = mb_strtolower($data['nis']);
                $emailKey = mb_strtolower($data['email']);
                if ($data['nis'] !== '' && in_array($nisKey, $existingNis, true)) {
                    $errors[] = 'NIS sudah terdaftar di OrbitFlow.';
                } elseif ($data['nis'] !== '' && isset($seenNis[$nisKey])) {
                    $errors[] = 'NIS duplikat di dalam file CSV.';
                }

                if ($data['email'] !== '' && in_array($emailKey, $existingEmails, true)) {
                    $errors[] = 'Email sudah digunakan oleh akun lain.';
                } elseif ($data['email'] !== '' && isset($seenEmails[$emailKey])) {
                    $errors[] = 'Email duplikat di dalam file CSV.';
                }

                if ($errors === []) {
                    if ($data['nis'] !== '') {
                        $seenNis[$nisKey] = true;
                    }
                    if ($data['email'] !== '') {
                        $seenEmails[$emailKey] = true;
                    }
                }

                $rows[] = [
                    'row_number' => $recordNumber,
                    'data' => $data,
                    'is_valid' => $errors === [],
                    'errors' => $errors,
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['csv_file' => 'CSV tidak memiliki baris data siswa.']);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function loadBatch(string $batch): array
    {
        abort_unless(Str::isUuid($batch), 404);

        $path = $this->batchPath($batch);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $payload = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        abort_unless((int) ($payload['created_by'] ?? 0) === (int) auth()->id(), 403);

        if ((int) ($payload['created_at'] ?? 0) < now()->subMinutes(self::BATCH_TTL_MINUTES)->timestamp) {
            Storage::disk('local')->delete($path);
            abort(410, 'Pratinjau impor sudah kedaluwarsa. Silakan unggah ulang file CSV.');
        }

        return $payload;
    }

    private function pruneExpiredBatches(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->directoryExists('student-imports')) {
            return;
        }

        foreach ($disk->files('student-imports') as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }
            $payload = json_decode($disk->get($path), true);
            if (! is_array($payload) || (int) ($payload['created_at'] ?? 0) < now()->subMinutes(self::BATCH_TTL_MINUTES)->timestamp) {
                $disk->delete($path);
            }
        }
    }

    private function batchPath(string $batch): string
    {
        return 'student-imports/' . $batch . '.json';
    }

    /** @param array<int, array<string, string>> $rows */
    private function csvDownload(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers);

            foreach ($rows as $row) {
                $values = [];
                foreach ($headers as $header) {
                    $value = (string) ($row[$header] ?? '');
                    // Prevent spreadsheet formula injection in exported cells.
                    if (preg_match('/^[\s]*[=+@\-]/u', $value)) {
                        $value = "'" . $value;
                    }
                    $values[] = $value;
                }
                fputcsv($stream, $values);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
