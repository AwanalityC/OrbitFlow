<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\OrbitFlowBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminBackupController extends Controller
{
    public function __construct(private readonly OrbitFlowBackupService $backups)
    {
    }

    public function index()
    {
        return view('admin.backups.index', [
            'backups' => $this->backups->listBackups(),
            'retentionDays' => max(1, (int) config('orbitflow-backups.retention_days', 30)),
            'scheduleTime' => (string) config('orbitflow-backups.schedule_time', '01:00'),
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $backup = $this->backups->createBackup('manual');

            ActivityLogger::record(
                'backup',
                'backup.created',
                'Backup manual OrbitFlow berhasil dibuat.',
                null,
                null,
                [
                    'filename' => $backup['filename'],
                    'size_bytes' => $backup['size_bytes'],
                    'database_driver' => $backup['database_driver'],
                    'storage_file_count' => $backup['storage_file_count'],
                    'private_evidence_file_count' => $backup['private_evidence_file_count'] ?? 0,
                ],
                auth()->user(),
                $backup['filename']
            );

            return redirect()->route('admin.backups.index')
                ->with('success', 'Backup berhasil dibuat: ' . $backup['filename']);
        } catch (Throwable $exception) {
            Log::error('OrbitFlow manual backup failed.', ['exception' => $exception]);
            ActivityLogger::record(
                'backup',
                'backup.failed',
                'Pembuatan backup manual OrbitFlow gagal.',
                null,
                null,
                ['error_type' => class_basename($exception)],
                auth()->user(),
                'Backup manual'
            );

            return redirect()->route('admin.backups.index')
                ->withErrors(['backup' => 'Backup gagal dibuat. Periksa ekstensi PHP ZIP, konfigurasi database, lokasi mysqldump, dan log aplikasi.']);
        }
    }

    public function verify(string $filename): RedirectResponse
    {
        try {
            $result = $this->backups->verifyBackup($filename);

            ActivityLogger::record(
                'backup',
                'backup.verified',
                'Integritas backup OrbitFlow berhasil diverifikasi.',
                null,
                null,
                [
                    'filename' => $filename,
                    'database_driver' => $result['database_driver'],
                    'storage_file_count' => $result['storage_file_count'],
                    'private_evidence_file_count' => $result['private_evidence_file_count'] ?? 0,
                ],
                auth()->user(),
                $filename
            );

            return redirect()->route('admin.backups.index')
                ->with('success', 'Backup terverifikasi. Checksum database, file storage publik, dan bukti kehilangan privat cocok dengan manifest.');
        } catch (Throwable $exception) {
            Log::warning('OrbitFlow backup verification failed.', [
                'filename' => $filename,
                'exception' => $exception,
            ]);

            ActivityLogger::record(
                'backup',
                'backup.verification_failed',
                'Pemeriksaan integritas backup OrbitFlow gagal.',
                null,
                null,
                ['filename' => $filename],
                auth()->user(),
                $filename
            );

            return redirect()->route('admin.backups.index')
                ->withErrors(['backup' => 'Verifikasi gagal. Backup mungkin rusak, tidak lengkap, atau dibuat dengan APP_KEY yang berbeda.']);
        }
    }

    public function download(string $filename)
    {
        $path = $this->backups->absolutePath($filename);

        ActivityLogger::record(
            'backup',
            'backup.downloaded',
            'Backup OrbitFlow diunduh oleh Super Admin.',
            null,
            null,
            ['filename' => $filename],
            auth()->user(),
            $filename
        );

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
