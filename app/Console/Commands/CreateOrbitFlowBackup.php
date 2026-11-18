<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\OrbitFlowBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateOrbitFlowBackup extends Command
{
    protected $signature = 'orbitflow:backup {--scheduled : Mark this as an automatic scheduled backup}';

    protected $description = 'Create an encrypted OrbitFlow database and public-storage backup.';

    public function handle(OrbitFlowBackupService $backups): int
    {
        try {
            $purpose = $this->option('scheduled') ? 'scheduled' : 'manual';
            $backup = $backups->createBackup($purpose);

            ActivityLogger::recordAnonymous(
                'backup',
                'backup.created',
                $this->option('scheduled')
                    ? 'Backup otomatis OrbitFlow berhasil dibuat.'
                    : 'Backup OrbitFlow berhasil dibuat melalui Artisan.',
                [
                    'filename' => $backup['filename'],
                    'size_bytes' => $backup['size_bytes'],
                    'database_driver' => $backup['database_driver'],
                    'storage_file_count' => $backup['storage_file_count'],
                    'scheduled' => (bool) $this->option('scheduled'),
                ],
                $backup['filename']
            );

            $this->info('Backup berhasil dibuat: ' . $backup['filename']);
            $this->line('Lokasi: ' . $backup['path']);
            $this->line('Ukuran: ' . number_format($backup['size_bytes']) . ' bytes');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            ActivityLogger::recordAnonymous(
                'backup',
                'backup.failed',
                'Pembuatan backup OrbitFlow gagal.',
                ['error_type' => class_basename($exception)],
                'Backup gagal'
            );
            $this->error('Backup gagal: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
