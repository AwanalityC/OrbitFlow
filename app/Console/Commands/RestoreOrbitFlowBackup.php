<?php

namespace App\Console\Commands;

use App\Services\OrbitFlowBackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreOrbitFlowBackup extends Command
{
    protected $signature = 'orbitflow:backup:restore {filename : Backup filename stored in OrbitFlow private backup storage} {--yes : Skip the interactive confirmation}';

    protected $description = 'Verify and restore an encrypted OrbitFlow backup. CLI only.';

    public function handle(OrbitFlowBackupService $backups): int
    {
        $filename = (string) $this->argument('filename');

        try {
            $verified = $backups->verifyBackup($filename);
        } catch (Throwable $exception) {
            $this->error('Backup tidak dapat diverifikasi: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->warn('PERINGATAN: pemulihan akan mengganti database aktif dan storage/app/public dengan isi backup.');
        $this->line('File: ' . $filename);
        $this->line('Dibuat: ' . $verified['created_at_wib']);
        $this->line('Database: ' . $verified['database_driver'] . ' / ' . $verified['database_name']);
        $this->line('Seluruh data saat ini akan diamankan lebih dahulu dalam backup pre-restore.');

        if (! $this->option('yes') && ! $this->confirm('Apakah Anda yakin ingin melanjutkan pemulihan?', false)) {
            $this->comment('Pemulihan dibatalkan. Tidak ada data yang diubah.');

            return self::SUCCESS;
        }

        try {
            $result = $backups->restoreBackup($filename);
            $this->info('Pemulihan berhasil pada ' . $result['restored_at']);
            $this->line('Backup sebelum pemulihan: ' . $result['pre_restore_backup']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
