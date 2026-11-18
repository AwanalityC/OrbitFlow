<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Compensation;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdminLossController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:Semua,Dilaporkan,Dikonfirmasi,Hilang Permanen,Ditolak,DitemukanKembali'],
        ]);

        $query = Loss::with([
            'borrowing.user',
            'equipmentUnit.equipment',
            'reviewer',
            'compensation.decider',
            'compensation.resolver',
            'compensation.replacementEquipmentUnit',
        ])
            ->latest('reported_at');

        $search = trim($validated['q'] ?? '');
        $status = $validated['status'] ?? 'Dilaporkan';

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', '%' . $search . '%')
                    ->orWhereHas('borrowing.user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%'))
                    ->orWhereHas('equipmentUnit', fn ($unitQuery) => $unitQuery->where('unit_code', 'like', '%' . $search . '%'));
            });
        }

        if ($status !== 'Semua') {
            $query->where('status', $status);
        }

        $losses = $query->paginate(15)->withQueryString();

        // Live search only needs the result fragment. Avoid rendering the admin
        // layout, navigation, dashboard cards, and their extra queries per keystroke.
        if ($request->header('X-OrbitFlow-Live-Filter') === 'partial') {
            return response()
                ->view('admin.partials.loss-results', compact('losses'))
                ->header('Cache-Control', 'private, no-store');
        }

        // Fetch all three summary counts with one grouped query on the full page load.
        $statusCounts = Loss::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingCount = (int) $statusCounts->get('Dilaporkan', 0);
        $confirmedCount = (int) $statusCounts->get('Dikonfirmasi', 0);
        $rejectedCount = (int) $statusCounts->get('Ditolak', 0);
        $recoveredCount = (int) $statusCounts->get('DitemukanKembali', 0);
        $permanentLossCount = (int) $statusCounts->get('Hilang Permanen', 0);

        return view('admin.losses', compact(
            'losses', 'search', 'status', 'pendingCount', 'confirmedCount', 'rejectedCount', 'recoveredCount', 'permanentLossCount'
        ));
    }

    public function evidence(Loss $loss): Response
    {
        abort_unless($loss->evidence_photo_path, 404);
        abort_unless(Storage::disk('local')->exists($loss->evidence_photo_path), 404);

        return response()->file(Storage::disk('local')->path($loss->evidence_photo_path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function confirm(Request $request, Loss $loss)
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $unitCode = null;
        $borrowingStatusBefore = null;

        DB::transaction(function () use ($request, $loss, $validated, &$unitCode, &$borrowingStatusBefore) {
            $lockedLoss = Loss::whereKey($loss->id)->lockForUpdate()->firstOrFail();

            if ($lockedLoss->status !== 'Dilaporkan') {
                throw ValidationException::withMessages([
                    'review_notes' => 'Laporan ini sudah pernah diproses.',
                ]);
            }

            $borrowing = Borrowing::whereKey($lockedLoss->borrowing_id)->lockForUpdate()->firstOrFail();
            $unit = EquipmentUnit::whereKey($lockedLoss->equipment_unit_id)->lockForUpdate()->firstOrFail();

            $allocation = DB::table('borrowing_item_equipment_unit')
                ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
                ->where('borrowing_items.borrowing_id', $borrowing->id)
                ->where('borrowing_item_equipment_unit.equipment_unit_id', $unit->id)
                ->whereNull('borrowing_item_equipment_unit.returned_at')
                ->where('borrowing_item_equipment_unit.active_equipment_unit_id', $unit->id)
                ->lockForUpdate()
                ->first();

            if (! $allocation || $unit->status !== 'Dipinjam') {
                throw ValidationException::withMessages([
                    'review_notes' => 'Unit tidak lagi tercatat sebagai alokasi peminjaman aktif. Periksa riwayat unit sebelum mengonfirmasi laporan.',
                ]);
            }

            $unitCode = $unit->unit_code;
            $borrowingStatusBefore = $borrowing->status;

            $unit->update([
                'condition' => 'Hilang',
                'status' => 'Hilang',
            ]);

            // Close the active inventory claim without pretending that the item was physically returned.
            DB::table('borrowing_item_equipment_unit')
                ->where('borrowing_item_id', $allocation->borrowing_item_id)
                ->where('equipment_unit_id', $unit->id)
                ->whereNull('returned_at')
                ->update([
                    'active_equipment_unit_id' => null,
                    'updated_at' => now(),
                ]);

            $lockedLoss->update([
                'status' => 'Dikonfirmasi',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);

            Compensation::firstOrCreate(
                ['loss_id' => $lockedLoss->id],
                [
                    'user_id' => $borrowing->user_id,
                    'return_record_id' => null,
                    'type' => 'Kehilangan Alat',
                    'description' => 'Kehilangan unit ' . $unit->unit_code . ' dikonfirmasi. Admin dapat mencatat penggantian, pembayaran, pembebasan, atau penemuan kembali sesuai kebijakan sekolah.',
                    'status' => 'Pending',
                ]
            );

            $hasActiveUnits = DB::table('borrowing_item_equipment_unit')
                ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
                ->where('borrowing_items.borrowing_id', $borrowing->id)
                ->whereNotNull('borrowing_item_equipment_unit.active_equipment_unit_id')
                ->exists();

            if (! $hasActiveUnits) {
                $borrowing->update(['status' => 'CompletedWithLoss']);
            }
        });

        ActivityLogger::record(
            'losses',
            'loss.confirmed',
            'Laporan kehilangan unit ' . $unitCode . ' dikonfirmasi.',
            $loss->fresh(['borrowing', 'equipmentUnit']),
            ['status' => 'Dilaporkan'],
            ['status' => 'Dikonfirmasi', 'borrowing_status_before' => $borrowingStatusBefore],
            $request->user(),
            $unitCode
        );

        return redirect()->route('admin.losses.index')
            ->with('success', 'Laporan dikonfirmasi. Unit ditandai Hilang dan catatan tindak lanjut dibuat tanpa nominal otomatis.');
    }

    public function setCompensationPlan(Request $request, Loss $loss)
    {
        $validated = $request->validate([
            'resolution_type' => ['required', 'in:Ganti Barang,Bayar Kompensasi,Dibebaskan,Barang Ditemukan'],
            'amount' => ['nullable', 'required_if:resolution_type,Bayar Kompensasi', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'recovered_condition' => ['nullable', 'required_if:resolution_type,Barang Ditemukan', 'in:Baik,Rusak Ringan,Rusak Berat'],
            'resolution_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'resolution_type.required' => 'Pilih salah satu tindak lanjut kompensasi.',
            'amount.required_if' => 'Nominal kompensasi wajib diisi untuk pilihan pembayaran.',
            'amount.min' => 'Nominal kompensasi harus lebih dari Rp0.',
            'recovered_condition.required_if' => 'Pilih kondisi unit yang ditemukan kembali.',
            'resolution_notes.required' => 'Catatan keputusan wajib diisi.',
            'resolution_notes.min' => 'Catatan keputusan minimal 5 karakter.',
        ]);

        $before = null;
        $after = null;
        $unitCode = null;
        $borrowingStatusAfter = null;
        $resolutionType = $validated['resolution_type'];

        DB::transaction(function () use ($request, $loss, $validated, $resolutionType, &$before, &$after, &$unitCode, &$borrowingStatusAfter) {
            $lockedLoss = Loss::whereKey($loss->id)->lockForUpdate()->firstOrFail();

            if ($lockedLoss->status !== 'Dikonfirmasi') {
                throw ValidationException::withMessages([
                    'resolution_type' => 'Tindak lanjut kompensasi hanya dapat dipilih untuk laporan yang sudah dikonfirmasi dan belum ditemukan kembali.',
                ]);
            }

            $compensation = Compensation::where('loss_id', $lockedLoss->id)
                ->lockForUpdate()
                ->first();

            if (! $compensation || $compensation->status !== 'Pending' || $compensation->resolution_type) {
                throw ValidationException::withMessages([
                    'resolution_type' => 'Catatan kompensasi ini sudah memiliki keputusan atau tidak tersedia.',
                ]);
            }

            $before = [
                'status' => $compensation->status,
                'resolution_type' => $compensation->resolution_type,
                'loss_status' => $lockedLoss->status,
            ];

            $now = now();
            $newStatus = match ($validated['resolution_type']) {
                'Ganti Barang' => 'Menunggu Penggantian',
                'Bayar Kompensasi' => 'Menunggu Pembayaran',
                'Dibebaskan', 'Barang Ditemukan' => 'Selesai',
            };

            if ($validated['resolution_type'] === 'Barang Ditemukan') {
                $borrowing = Borrowing::whereKey($lockedLoss->borrowing_id)->lockForUpdate()->firstOrFail();
                $unit = EquipmentUnit::whereKey($lockedLoss->equipment_unit_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($unit->status !== 'Hilang' || $unit->condition !== 'Hilang') {
                    throw ValidationException::withMessages([
                        'recovered_condition' => 'Unit tidak lagi tercatat hilang. Periksa kondisi inventaris sebelum melanjutkan.',
                    ]);
                }

                $unitCode = $unit->unit_code;
                $condition = $validated['recovered_condition'];
                $unit->update([
                    'condition' => $condition,
                    'status' => $condition === 'Rusak Berat' ? 'Tidak Tersedia' : 'Tersedia',
                ]);

                $recoveredAt = now();
                $allocationUpdated = DB::table('borrowing_item_equipment_unit')
                    ->whereIn('borrowing_item_id', DB::table('borrowing_items')
                        ->select('id')
                        ->where('borrowing_id', $lockedLoss->borrowing_id))
                    ->where('equipment_unit_id', $unit->id)
                    ->whereNull('returned_at')
                    ->update([
                        'active_equipment_unit_id' => null,
                        'returned_at' => $recoveredAt,
                        'updated_at' => $recoveredAt,
                    ]);

                if ($allocationUpdated === 0) {
                    throw ValidationException::withMessages([
                        'recovered_condition' => 'Riwayat alokasi unit tidak ditemukan. Periksa riwayat peminjaman sebelum memulihkan unit.',
                    ]);
                }

                $lockedLoss->update(['status' => 'DitemukanKembali']);

                $hasActiveUnits = DB::table('borrowing_item_equipment_unit')
                    ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
                    ->where('borrowing_items.borrowing_id', $borrowing->id)
                    ->whereNotNull('borrowing_item_equipment_unit.active_equipment_unit_id')
                    ->exists();

                if (! $hasActiveUnits) {
                    $hasOtherLostUnits = Loss::where('borrowing_id', $borrowing->id)
                        ->whereIn('status', ['Dikonfirmasi', 'Hilang Permanen'])
                        ->exists();

                    $borrowing->update([
                        'status' => $hasOtherLostUnits ? 'CompletedWithLoss' : 'Returned',
                    ]);
                }
                $borrowingStatusAfter = $borrowing->fresh()->status;
            } else {
                // Once the school chooses a permanent follow-up path, keep the
                // asset recorded as permanently lost even after compensation settles.
                $lockedLoss->update(['status' => 'Hilang Permanen']);
            }

            $autoResolved = in_array($validated['resolution_type'], ['Dibebaskan', 'Barang Ditemukan'], true);

            $compensation->update([
                'resolution_type' => $validated['resolution_type'],
                'amount' => $validated['resolution_type'] === 'Bayar Kompensasi' ? $validated['amount'] : null,
                'resolution_notes' => $validated['resolution_notes'],
                'recovered_condition' => $validated['resolution_type'] === 'Barang Ditemukan'
                    ? $validated['recovered_condition']
                    : null,
                'status' => $newStatus,
                'decided_by' => $request->user()->id,
                'decided_at' => $now,
                'resolved_by' => $autoResolved ? $request->user()->id : null,
                'resolved_at' => $autoResolved ? $now : null,
            ]);

            $after = [
                'status' => $newStatus,
                'resolution_type' => $validated['resolution_type'],
                'amount' => $validated['resolution_type'] === 'Bayar Kompensasi' ? $validated['amount'] : null,
                'loss_status' => $lockedLoss->fresh()->status,
                'recovered_condition' => $validated['recovered_condition'] ?? null,
                'borrowing_status' => $borrowingStatusAfter,
            ];
        });

        ActivityLogger::record(
            'losses',
            'loss.compensation.decided',
            'Keputusan tindak lanjut kehilangan unit ' . ($unitCode ?: ($loss->equipmentUnit?->unit_code ?? 'tidak diketahui')) . ': ' . $resolutionType . '.',
            $loss->fresh(),
            $before ?? [],
            $after ?? [],
            $request->user(),
            $unitCode ?: $loss->equipmentUnit?->unit_code
        );

        $message = match ($resolutionType) {
            'Ganti Barang' => 'Keputusan penggantian disimpan. Tunggu sampai unit pengganti diterima dan dicatat ke inventaris.',
            'Bayar Kompensasi' => 'Nominal kompensasi disimpan sesuai keputusan admin. Status menunggu pembayaran, tidak dianggap lunas.',
            'Dibebaskan' => 'Kompensasi dibebaskan dengan alasan yang tercatat.',
            'Barang Ditemukan' => 'Unit ditemukan kembali. Kondisi inventaris diperbarui berdasarkan hasil pemeriksaan.',
        };

        return redirect()->route('admin.losses.index')->with('success', $message);
    }

    public function completeCompensation(Request $request, Loss $loss)
    {
        $validated = $request->validate([
            'completion_notes' => ['required', 'string', 'min:5', 'max:1000'],
            'replacement_unit_code' => ['nullable', 'string', 'max:100', 'unique:equipment_units,unit_code'],
            'replacement_condition' => ['nullable', 'in:Baik,Rusak Ringan'],
        ], [
            'completion_notes.required' => 'Catatan penyelesaian wajib diisi.',
            'completion_notes.min' => 'Catatan penyelesaian minimal 5 karakter.',
            'replacement_unit_code.unique' => 'Kode unit pengganti sudah digunakan.',
        ]);

        $before = null;
        $after = null;
        $unitCode = null;

        DB::transaction(function () use ($request, $loss, $validated, &$before, &$after, &$unitCode) {
            $lockedLoss = Loss::whereKey($loss->id)->lockForUpdate()->firstOrFail();
            $compensation = Compensation::where('loss_id', $lockedLoss->id)
                ->lockForUpdate()
                ->first();

            if (! $compensation || ! in_array($compensation->status, ['Menunggu Pembayaran', 'Menunggu Penggantian'], true)) {
                throw ValidationException::withMessages([
                    'completion_notes' => 'Catatan ini tidak sedang menunggu pembayaran atau penggantian.',
                ]);
            }

            if (
                ($compensation->status === 'Menunggu Pembayaran' && $compensation->resolution_type !== 'Bayar Kompensasi')
                || ($compensation->status === 'Menunggu Penggantian' && $compensation->resolution_type !== 'Ganti Barang')
            ) {
                throw ValidationException::withMessages([
                    'completion_notes' => 'Jenis tindak lanjut tidak sesuai dengan status kompensasi. Periksa kembali catatan ini.',
                ]);
            }

            $before = [
                'status' => $compensation->status,
                'resolution_type' => $compensation->resolution_type,
                'amount' => $compensation->amount,
            ];

            if ($compensation->status === 'Menunggu Penggantian') {
                if (blank($validated['replacement_unit_code'] ?? null)) {
                    throw ValidationException::withMessages([
                        'replacement_unit_code' => 'Kode unit pengganti wajib diisi setelah barang diterima.',
                    ]);
                }

                if (blank($validated['replacement_condition'] ?? null)) {
                    throw ValidationException::withMessages([
                        'replacement_condition' => 'Pilih kondisi unit pengganti.',
                    ]);
                }

                $lostUnit = EquipmentUnit::whereKey($lockedLoss->equipment_unit_id)->first();
                if (! $lostUnit) {
                    throw ValidationException::withMessages([
                        'replacement_unit_code' => 'Data alat asal tidak tersedia sehingga unit pengganti tidak dapat dimasukkan ke inventaris.',
                    ]);
                }

                $replacement = EquipmentUnit::create([
                    'equipment_id' => $lostUnit->equipment_id,
                    'unit_code' => trim($validated['replacement_unit_code']),
                    'condition' => $validated['replacement_condition'],
                    'status' => 'Tersedia',
                ]);
                $compensation->replacement_equipment_unit_id = $replacement->id;
                $unitCode = $replacement->unit_code;
            }

            $now = now();
            $compensation->update([
                'status' => 'Selesai',
                'completion_notes' => $validated['completion_notes'],
                'resolved_by' => $request->user()->id,
                'resolved_at' => $now,
            ]);

            $after = [
                'status' => 'Selesai',
                'resolution_type' => $compensation->resolution_type,
                'amount' => $compensation->amount,
                'replacement_unit_code' => $unitCode,
                'completion_notes' => $validated['completion_notes'],
            ];
        });

        ActivityLogger::record(
            'losses',
            'loss.compensation.completed',
            'Tindak lanjut kompensasi kehilangan unit ' . ($loss->equipmentUnit?->unit_code ?? 'tidak diketahui') . ' diselesaikan.',
            $loss->fresh(),
            $before ?? [],
            $after ?? [],
            $request->user(),
            $unitCode ?: $loss->equipmentUnit?->unit_code
        );

        return redirect()->route('admin.losses.index')
            ->with('success', 'Tindak lanjut kompensasi berhasil diselesaikan dan dicatat.');
    }

    public function reject(Request $request, Loss $loss)
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'review_notes.required' => 'Alasan penolakan wajib diisi.',
            'review_notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        DB::transaction(function () use ($request, $loss, $validated) {
            $lockedLoss = Loss::whereKey($loss->id)->lockForUpdate()->firstOrFail();

            if ($lockedLoss->status !== 'Dilaporkan') {
                throw ValidationException::withMessages([
                    'review_notes' => 'Laporan ini sudah pernah diproses.',
                ]);
            }

            $lockedLoss->update([
                'status' => 'Ditolak',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'],
            ]);
        });

        ActivityLogger::record(
            'losses',
            'loss.rejected',
            'Laporan kehilangan unit ' . ($loss->equipmentUnit?->unit_code ?? 'tidak diketahui') . ' ditolak.',
            $loss->fresh(),
            ['status' => 'Dilaporkan'],
            ['status' => 'Ditolak', 'review_notes' => $validated['review_notes']],
            $request->user(),
            $loss->equipmentUnit?->unit_code
        );

        return redirect()->route('admin.losses.index')
            ->with('success', 'Laporan ditolak. Unit tetap tercatat sedang dipinjam dan harus dikembalikan melalui proses normal.');
    }
}
