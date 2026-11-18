<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class OrbitFlowBackupService
{
    private const FORMAT = 'orbitflow-backup';
    private const FORMAT_VERSION = 1;
    private const BACKUP_FILENAME_PATTERN = '/^orbitflow-backup-(manual|scheduled|pre-restore)-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.zip$/';

    public function listBackups(): array
    {
        $directory = $this->backupDirectory();
        if (! is_dir($directory)) {
            return [];
        }

        $backups = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . 'orbitflow-backup-*.zip') ?: [] as $path) {
            $filename = basename($path);
            if (! $this->isValidFilename($filename) || ! is_file($path) || is_link($path)) {
                continue;
            }

            $backups[] = [
                'filename' => $filename,
                'size_bytes' => (int) filesize($path),
                'modified_at' => (int) filemtime($path),
            ];
        }

        usort($backups, fn (array $a, array $b) => $b['modified_at'] <=> $a['modified_at']);

        return $backups;
    }

    /**
     * Create an encrypted, self-describing backup archive.
     * The APP_KEY is used to derive the archive encryption password. Keep a
     * separate, secure copy of .env so the same key remains available on restore.
     */
    public function createBackup(string $purpose = 'manual'): array
    {
        if (! in_array($purpose, ['manual', 'scheduled', 'pre-restore'], true)) {
            throw new RuntimeException('Jenis backup tidak dikenal.');
        }

        $this->assertZipSupport();
        $encryptionPassword = $this->backupEncryptionPassword();
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory, 0700, true);

        $workDirectory = $directory . DIRECTORY_SEPARATOR . '.work-' . Str::uuid();
        File::ensureDirectoryExists($workDirectory, 0700, true);

        $filename = sprintf(
            'orbitflow-backup-%s-%s-%s.zip',
            $purpose,
            now()->format('Ymd-His'),
            bin2hex(random_bytes(4))
        );
        $finalPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $partialPath = $finalPath . '.partial';

        try {
            $database = $this->createDatabaseSnapshot($workDirectory);
            $storageFiles = $this->collectPublicStorageFiles();
            // Only loss evidence is copied from the private disk. CSV previews,
            // activation exports, and other temporary private files stay excluded.
            $privateEvidenceFiles = $this->collectPrivateLossEvidenceFiles();

            $manifest = [
                'format' => self::FORMAT,
                'format_version' => self::FORMAT_VERSION,
                'application' => config('app.name', 'OrbitFlow'),
                'created_at_utc' => now()->utc()->toIso8601String(),
                'created_at_wib' => now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' WIB',
                'database' => [
                    'driver' => $database['driver'],
                    'database_name' => $database['database_name'],
                    'file' => $database['archive_path'],
                    'sha256' => hash_file('sha256', $database['absolute_path']),
                    'size_bytes' => (int) filesize($database['absolute_path']),
                ],
                'storage' => [
                    'disk' => 'public',
                    'files' => $storageFiles['manifest'],
                    'file_count' => count($storageFiles['manifest']),
                    'total_bytes' => $storageFiles['total_bytes'],
                ],
                'private_evidence' => [
                    'directory' => 'loss-evidence',
                    'files' => $privateEvidenceFiles['manifest'],
                    'file_count' => count($privateEvidenceFiles['manifest']),
                    'total_bytes' => $privateEvidenceFiles['total_bytes'],
                ],
                'encryption' => 'AES-256 (derived from APP_KEY)',
                'excluded' => [
                    '.env and environment secrets',
                    'storage/app/private except the whitelisted loss-evidence folder (temporary CSV previews and one-time activation exports stay excluded)',
                    'logs, cache, sessions, vendor and node_modules',
                ],
            ];

            $zip = new ZipArchive();
            $opened = $zip->open($partialPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($opened !== true) {
                throw new RuntimeException('Arsip backup tidak dapat dibuat. Periksa izin folder penyimpanan.');
            }

            try {
                if (! $zip->setPassword($encryptionPassword)) {
                    throw new RuntimeException('Enkripsi arsip backup tidak dapat diaktifkan.');
                }

                $this->addEncryptedFile($zip, $database['absolute_path'], $database['archive_path']);

                foreach ($storageFiles['absolute_files'] as $relativePath => $absolutePath) {
                    $this->addEncryptedFile($zip, $absolutePath, 'storage/app/public/' . $relativePath);
                }

                foreach ($privateEvidenceFiles['absolute_files'] as $relativePath => $absolutePath) {
                    $this->addEncryptedFile($zip, $absolutePath, 'storage/app/private/loss-evidence/' . $relativePath);
                }

                $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (! $zip->addFromString('manifest.json', $manifestJson)) {
                    throw new RuntimeException('Manifest backup tidak dapat ditulis.');
                }
                if (! $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256)) {
                    throw new RuntimeException('Enkripsi manifest backup gagal.');
                }
            } finally {
                $closed = $zip->close();
                if ($closed !== true) {
                    throw new RuntimeException('Arsip backup gagal diselesaikan.');
                }
            }

            if (! is_file($partialPath) || filesize($partialPath) < 100) {
                throw new RuntimeException('Arsip backup tidak lengkap.');
            }

            if (! rename($partialPath, $finalPath)) {
                throw new RuntimeException('Arsip backup tidak dapat dipindahkan ke folder penyimpanan.');
            }

            $this->pruneExpiredBackups();

            return [
                'filename' => $filename,
                'path' => $finalPath,
                'size_bytes' => (int) filesize($finalPath),
                'created_at' => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . ' WIB',
                'database_driver' => $database['driver'],
                'storage_file_count' => count($storageFiles['manifest']),
                'private_evidence_file_count' => count($privateEvidenceFiles['manifest']),
            ];
        } catch (Throwable $exception) {
            if (is_file($partialPath)) {
                @unlink($partialPath);
            }
            throw $exception;
        } finally {
            if (is_dir($workDirectory)) {
                File::deleteDirectory($workDirectory);
            }
        }
    }

    public function verifyBackup(string $filename): array
    {
        $this->assertZipSupport();
        $path = $this->absolutePath($filename);
        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new RuntimeException('Arsip backup rusak atau tidak dapat dibuka.');
        }

        try {
            if (! $zip->setPassword($this->backupEncryptionPassword())) {
                throw new RuntimeException('Kunci enkripsi backup tidak dapat diterapkan. Periksa APP_KEY.');
            }

            $manifestContent = $zip->getFromName('manifest.json');
            if (! is_string($manifestContent)) {
                throw new RuntimeException('Manifest backup tidak ditemukan atau tidak dapat didekripsi. Pastikan APP_KEY sama dengan saat backup dibuat.');
            }

            $manifest = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== self::FORMAT || (int) ($manifest['format_version'] ?? 0) !== self::FORMAT_VERSION) {
                throw new RuntimeException('Format backup tidak didukung.');
            }

            $database = $manifest['database'] ?? null;
            if (! is_array($database)) {
                throw new RuntimeException('Informasi database dalam manifest tidak valid.');
            }

            $driver = (string) ($database['driver'] ?? '');
            $expectedDatabasePath = match ($driver) {
                'sqlite' => 'database/database.sqlite',
                'mysql', 'mariadb' => 'database/database.sql',
                default => throw new RuntimeException('Jenis database dalam backup tidak didukung.'),
            };

            if (($database['file'] ?? null) !== $expectedDatabasePath) {
                throw new RuntimeException('Lokasi file database dalam manifest tidak valid.');
            }

            $databaseHash = $this->hashZipEntry($zip, $expectedDatabasePath);
            if (! hash_equals((string) ($database['sha256'] ?? ''), $databaseHash['sha256'])) {
                throw new RuntimeException('Pemeriksaan checksum database gagal. Backup mungkin rusak atau berubah.');
            }
            if ((int) ($database['size_bytes'] ?? -1) !== $databaseHash['size_bytes']) {
                throw new RuntimeException('Ukuran file database tidak cocok dengan manifest.');
            }

            $storage = $manifest['storage'] ?? ['files' => []];
            $storageFiles = $storage['files'] ?? [];
            if (! is_array($storageFiles)) {
                throw new RuntimeException('Daftar file storage dalam manifest tidak valid.');
            }

            foreach ($storageFiles as $file) {
                if (! is_array($file) || ! $this->isSafeRelativeStoragePath((string) ($file['path'] ?? ''))) {
                    throw new RuntimeException('Path file storage dalam backup tidak valid.');
                }

                $entry = 'storage/app/public/' . $file['path'];
                $hash = $this->hashZipEntry($zip, $entry);
                if (! hash_equals((string) ($file['sha256'] ?? ''), $hash['sha256']) || (int) ($file['size_bytes'] ?? -1) !== $hash['size_bytes']) {
                    throw new RuntimeException('Checksum file storage gagal: ' . $file['path']);
                }
            }

            // Older backup archives do not contain this section. They remain
            // verifiable, while newer backups include private loss evidence.
            $privateEvidence = $manifest['private_evidence'] ?? ['files' => []];
            $privateEvidenceFiles = $privateEvidence['files'] ?? [];
            if (! is_array($privateEvidenceFiles)) {
                throw new RuntimeException('Daftar bukti kehilangan dalam manifest tidak valid.');
            }

            foreach ($privateEvidenceFiles as $file) {
                if (! is_array($file) || ! $this->isSafeRelativeStoragePath((string) ($file['path'] ?? ''))) {
                    throw new RuntimeException('Path bukti kehilangan dalam backup tidak valid.');
                }

                $entry = 'storage/app/private/loss-evidence/' . $file['path'];
                $hash = $this->hashZipEntry($zip, $entry);
                if (! hash_equals((string) ($file['sha256'] ?? ''), $hash['sha256']) || (int) ($file['size_bytes'] ?? -1) !== $hash['size_bytes']) {
                    throw new RuntimeException('Checksum bukti kehilangan gagal: ' . $file['path']);
                }
            }

            return [
                'filename' => $filename,
                'created_at_wib' => (string) ($manifest['created_at_wib'] ?? 'Tidak diketahui'),
                'database_driver' => $driver,
                'database_name' => (string) ($database['database_name'] ?? ''),
                'database_size_bytes' => (int) $database['size_bytes'],
                'storage_file_count' => count($storageFiles),
                'storage_size_bytes' => (int) ($storage['total_bytes'] ?? 0),
                'private_evidence_file_count' => count($privateEvidenceFiles),
                'private_evidence_size_bytes' => (int) ($privateEvidence['total_bytes'] ?? 0),
                'verified' => true,
            ];
        } finally {
            $zip->close();
        }
    }

    public function absolutePath(string $filename): string
    {
        if (! $this->isValidFilename($filename)) {
            abort(404);
        }

        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $filename;
        abort_unless(is_file($path) && ! is_link($path), 404);

        return $path;
    }

    /**
     * Restore is deliberately CLI-only. This method creates a pre-restore
     * backup, verifies and stages the archive, enters maintenance mode, then
     * restores the database and public storage.
     */
    public function restoreBackup(string $filename): array
    {
        $verified = $this->verifyBackup($filename);
        $currentDriver = DB::connection()->getDriverName();
        if ($verified['database_driver'] !== $currentDriver) {
            throw new RuntimeException('Driver database backup tidak sama dengan database aktif. Restore dibatalkan.');
        }

        $preRestore = $this->createBackup('pre-restore');
        $workDirectory = $this->backupDirectory() . DIRECTORY_SEPARATOR . '.restore-' . Str::uuid();
        File::ensureDirectoryExists($workDirectory, 0700, true);
        $maintenanceEnabled = false;

        try {
            $staged = $this->extractVerifiedBackup($filename, $workDirectory);
            $downExitCode = Artisan::call('down', ['--retry' => 60]);
            if ($downExitCode !== 0) {
                throw new RuntimeException('Aplikasi tidak dapat masuk maintenance mode. Restore dibatalkan sebelum database diubah.');
            }
            $maintenanceEnabled = true;

            if ($verified['database_driver'] === 'sqlite') {
                $this->restoreSqliteDatabase($staged['database_path']);
            } else {
                $this->restoreMysqlDatabase($staged['database_path'], $workDirectory);
            }

            $this->replacePublicStorage($staged['public_directory']);
            $this->replacePrivateLossEvidence($staged['private_evidence_directory']);

            DB::purge(DB::getDefaultConnection());
            DB::connection()->select('SELECT 1');

            // A restored session table may contain sessions that were valid at
            // the backup time. Revoke them so every user must authenticate again.
            if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->delete();
            }

            $upExitCode = Artisan::call('up');
            if ($upExitCode !== 0) {
                throw new RuntimeException('Database berhasil dipulihkan, tetapi maintenance mode tidak dapat dinonaktifkan. Periksa aplikasi sebelum menjalankan php artisan up.');
            }
            $maintenanceEnabled = false;

            ActivityLogger::recordAnonymous(
                'backup',
                'backup.restored',
                'Backup OrbitFlow berhasil dipulihkan melalui Artisan.',
                [
                    'filename' => $filename,
                    'pre_restore_backup' => $preRestore['filename'],
                ],
                'Pemulihan backup berhasil'
            );

            return [
                'filename' => $filename,
                'pre_restore_backup' => $preRestore['filename'],
                'restored_at' => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . ' WIB',
            ];
        } catch (Throwable $exception) {
            if ($maintenanceEnabled) {
                throw new RuntimeException(
                    'Pemulihan gagal. Aplikasi dibiarkan dalam maintenance mode untuk mencegah penggunaan data yang mungkin tidak konsisten. Backup pengaman sebelum restore: ' . $preRestore['filename'] . '. Periksa log, pulihkan backup pengaman, lalu jalankan php artisan up setelah database terverifikasi.',
                    previous: $exception
                );
            }

            throw $exception;
        } finally {
            if (is_dir($workDirectory)) {
                File::deleteDirectory($workDirectory);
            }
        }
    }

    public function pruneExpiredBackups(): int
    {
        $directory = $this->backupDirectory();
        if (! is_dir($directory)) {
            return 0;
        }

        $retentionDays = max(1, (int) config('orbitflow-backups.retention_days', 30));
        $cutoff = now()->subDays($retentionDays)->timestamp;
        $deleted = 0;

        foreach (glob($directory . DIRECTORY_SEPARATOR . 'orbitflow-backup-*.zip') ?: [] as $path) {
            $filename = basename($path);
            if (! $this->isValidFilename($filename) || ! is_file($path) || is_link($path)) {
                continue;
            }

            if ((int) filemtime($path) < $cutoff && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function createDatabaseSnapshot(string $workDirectory): array
    {
        $connectionName = DB::getDefaultConnection();
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        // Use Laravel's resolved connection config so DB_URL is handled too.
        $connectionConfig = $connection->getConfig();

        if ($driver === 'sqlite') {
            if (DB::connection($connectionName)->transactionLevel() > 0) {
                throw new RuntimeException('Backup SQLite tidak dapat dibuat saat transaksi database masih berjalan.');
            }

            $configuredPath = (string) ($connectionConfig['database'] ?? '');
            if ($configuredPath === '' || $configuredPath === ':memory:') {
                throw new RuntimeException('Database SQLite in-memory tidak dapat dibackup. Gunakan database SQLite berbentuk file atau MySQL/MariaDB.');
            }

            $target = $workDirectory . DIRECTORY_SEPARATOR . 'database.sqlite';
            $pdo = DB::connection($connectionName)->getPdo();
            $pdo->exec('VACUUM INTO ' . $pdo->quote($target));

            return [
                'driver' => 'sqlite',
                'database_name' => basename($configuredPath),
                'archive_path' => 'database/database.sqlite',
                'absolute_path' => $target,
            ];
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backup saat ini mendukung database MySQL, MariaDB, dan SQLite berbentuk file. Driver aktif: ' . $driver);
        }

        $databaseName = (string) ($connectionConfig['database'] ?? '');
        if ($databaseName === '') {
            throw new RuntimeException('Nama database MySQL/MariaDB belum dikonfigurasi.');
        }

        $dumpBinary = $this->findMysqlBinary('dump');
        $credentialsFile = $this->writeMysqlOptionsFile($workDirectory, $connectionConfig);
        $target = $workDirectory . DIRECTORY_SEPARATOR . 'database.sql';

        $process = new Process([
            $dumpBinary,
            '--defaults-extra-file=' . $credentialsFile,
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--hex-blob',
            '--add-drop-table',
            '--result-file=' . $target,
            $databaseName,
        ], base_path(), null, null, (float) config('orbitflow-backups.process_timeout', 1800));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException('Dump database MySQL/MariaDB gagal. Periksa lokasi mysqldump, izin database, dan konfigurasi koneksi. ' . Str::limit($message, 500, ''));
        }

        return [
            'driver' => $driver,
            'database_name' => $databaseName,
            'archive_path' => 'database/database.sql',
            'absolute_path' => $target,
        ];
    }

    private function collectPublicStorageFiles(): array
    {
        $root = storage_path('app/public');
        $manifest = [];
        $absoluteFiles = [];
        $totalBytes = 0;

        if (! is_dir($root)) {
            return ['manifest' => [], 'absolute_files' => [], 'total_bytes' => 0];
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('Folder storage publik tidak dapat dibaca.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $realPath = realpath($absolutePath);
            if ($realPath === false || ! str_starts_with($realPath, $rootReal . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($realPath, strlen($rootReal) + 1));
            if (! $this->isSafeRelativeStoragePath($relative)) {
                continue;
            }

            $size = (int) filesize($realPath);
            $manifest[] = [
                'path' => $relative,
                'sha256' => hash_file('sha256', $realPath),
                'size_bytes' => $size,
            ];
            $absoluteFiles[$relative] = $realPath;
            $totalBytes += $size;
        }

        return [
            'manifest' => $manifest,
            'absolute_files' => $absoluteFiles,
            'total_bytes' => $totalBytes,
        ];
    }

    /**
     * Collect only the application's private loss-evidence folder.
     * Other private files (CSV previews, activation exports, etc.) are intentionally excluded.
     */
    private function collectPrivateLossEvidenceFiles(): array
    {
        $root = storage_path('app/private/loss-evidence');
        $manifest = [];
        $absoluteFiles = [];
        $totalBytes = 0;

        if (is_link($root)) {
            throw new RuntimeException('Folder bukti kehilangan privat tidak boleh berupa symbolic link.');
        }

        if (! is_dir($root)) {
            return ['manifest' => [], 'absolute_files' => [], 'total_bytes' => 0];
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('Folder bukti kehilangan privat tidak dapat dibaca.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $realPath = realpath($absolutePath);
            if ($realPath === false || ! str_starts_with($realPath, $rootReal . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($realPath, strlen($rootReal) + 1));
            if (! $this->isSafeRelativeStoragePath($relative)) {
                continue;
            }

            $size = (int) filesize($realPath);
            $manifest[] = [
                'path' => $relative,
                'sha256' => hash_file('sha256', $realPath),
                'size_bytes' => $size,
            ];
            $absoluteFiles[$relative] = $realPath;
            $totalBytes += $size;
        }

        return [
            'manifest' => $manifest,
            'absolute_files' => $absoluteFiles,
            'total_bytes' => $totalBytes,
        ];
    }

    private function addEncryptedFile(ZipArchive $zip, string $absolutePath, string $archivePath): void
    {
        if (! $zip->addFile($absolutePath, $archivePath)) {
            throw new RuntimeException('File tidak dapat ditambahkan ke backup: ' . $archivePath);
        }

        if (! $zip->setEncryptionName($archivePath, ZipArchive::EM_AES_256)) {
            throw new RuntimeException('Enkripsi AES-256 gagal untuk file backup: ' . $archivePath);
        }
    }

    private function hashZipEntry(ZipArchive $zip, string $entry): array
    {
        $stream = $zip->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException('File tidak ditemukan dalam backup: ' . $entry);
        }

        $context = hash_init('sha256');
        $size = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('File backup tidak dapat dibaca: ' . $entry);
                }
                if ($chunk === '') {
                    break;
                }
                hash_update($context, $chunk);
                $size += strlen($chunk);
            }
        } finally {
            fclose($stream);
        }

        return ['sha256' => hash_final($context), 'size_bytes' => $size];
    }

    private function extractVerifiedBackup(string $filename, string $workDirectory): array
    {
        $path = $this->absolutePath($filename);
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat dibuka.');
        }

        try {
            $zip->setPassword($this->backupEncryptionPassword());
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $databaseEntry = (string) $manifest['database']['file'];
            $databasePath = $workDirectory . DIRECTORY_SEPARATOR . basename($databaseEntry);
            $this->copyZipEntryToFile($zip, $databaseEntry, $databasePath);

            $publicDirectory = $workDirectory . DIRECTORY_SEPARATOR . 'public';
            File::ensureDirectoryExists($publicDirectory, 0700, true);

            foreach (($manifest['storage']['files'] ?? []) as $file) {
                $relative = (string) $file['path'];
                if (! $this->isSafeRelativeStoragePath($relative)) {
                    throw new RuntimeException('Path file storage tidak aman.');
                }

                $target = $publicDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                File::ensureDirectoryExists(dirname($target), 0700, true);
                $this->copyZipEntryToFile($zip, 'storage/app/public/' . $relative, $target);
            }

            $privateEvidenceDirectory = $workDirectory . DIRECTORY_SEPARATOR . 'private-loss-evidence';
            File::ensureDirectoryExists($privateEvidenceDirectory, 0700, true);

            foreach (($manifest['private_evidence']['files'] ?? []) as $file) {
                $relative = (string) $file['path'];
                if (! $this->isSafeRelativeStoragePath($relative)) {
                    throw new RuntimeException('Path bukti kehilangan tidak aman.');
                }

                $target = $privateEvidenceDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                File::ensureDirectoryExists(dirname($target), 0700, true);
                $this->copyZipEntryToFile($zip, 'storage/app/private/loss-evidence/' . $relative, $target);
            }

            return [
                'database_path' => $databasePath,
                'public_directory' => $publicDirectory,
                'private_evidence_directory' => $privateEvidenceDirectory,
            ];
        } finally {
            $zip->close();
        }
    }

    private function copyZipEntryToFile(ZipArchive $zip, string $entry, string $target): void
    {
        $input = $zip->getStream($entry);
        if ($input === false) {
            throw new RuntimeException('Bestand backup tidak dapat dibaca: ' . $entry);
        }

        $output = fopen($target, 'wb');
        if ($output === false) {
            fclose($input);
            throw new RuntimeException('File sementara restore tidak dapat dibuat.');
        }

        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('Ekstraksi file backup gagal: ' . $entry);
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function restoreSqliteDatabase(string $snapshotPath): void
    {
        $connectionName = DB::getDefaultConnection();
        $database = (string) config('database.connections.' . $connectionName . '.database');
        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('Restore SQLite memerlukan database berbentuk file.');
        }

        $target = $this->absoluteDatabasePath($database);
        File::ensureDirectoryExists(dirname($target), 0700, true);
        DB::purge($connectionName);
        if (! copy($snapshotPath, $target)) {
            throw new RuntimeException('File database SQLite tidak dapat diganti.');
        }
        @unlink($target . '-wal');
        @unlink($target . '-shm');
    }

    private function restoreMysqlDatabase(string $sqlPath, string $workDirectory): void
    {
        $connectionName = DB::getDefaultConnection();
        // Use the same resolved credentials as the live Laravel connection.
        $connectionConfig = DB::connection($connectionName)->getConfig();
        $databaseName = (string) ($connectionConfig['database'] ?? '');
        if ($databaseName === '') {
            throw new RuntimeException('Nama database target tidak dikonfigurasi.');
        }

        $mysqlBinary = $this->findMysqlBinary('client');
        $credentialsFile = $this->writeMysqlOptionsFile($workDirectory, $connectionConfig);
        $input = fopen($sqlPath, 'rb');
        if ($input === false) {
            throw new RuntimeException('File SQL backup tidak dapat dibaca.');
        }

        try {
            $process = new Process([
                $mysqlBinary,
                '--defaults-extra-file=' . $credentialsFile,
                '--default-character-set=utf8mb4',
                $databaseName,
            ], base_path(), null, $input, (float) config('orbitflow-backups.process_timeout', 1800));
            $process->run();

            if (! $process->isSuccessful()) {
                $message = trim($process->getErrorOutput() ?: $process->getOutput());
                throw new RuntimeException('Restore MySQL/MariaDB gagal. ' . Str::limit($message, 500, ''));
            }
        } finally {
            fclose($input);
        }
    }

    private function replacePublicStorage(string $stagedDirectory): void
    {
        $currentDirectory = storage_path('app/public');
        $previousDirectory = $this->backupDirectory() . DIRECTORY_SEPARATOR . '.public-before-restore-' . Str::uuid();
        File::ensureDirectoryExists(dirname($currentDirectory), 0700, true);

        $movedPrevious = false;
        if (is_dir($currentDirectory)) {
            if (! rename($currentDirectory, $previousDirectory)) {
                throw new RuntimeException('Folder storage publik saat ini tidak dapat diamankan sebelum restore.');
            }
            $movedPrevious = true;
        }

        if (! rename($stagedDirectory, $currentDirectory)) {
            if ($movedPrevious) {
                @rename($previousDirectory, $currentDirectory);
            }
            throw new RuntimeException('Folder storage publik dari backup tidak dapat dipulihkan.');
        }

        if ($movedPrevious && is_dir($previousDirectory)) {
            File::deleteDirectory($previousDirectory);
        }
    }

    /** Restore only the whitelisted private loss-evidence folder. */
    private function replacePrivateLossEvidence(string $stagedDirectory): void
    {
        $currentDirectory = storage_path('app/private/loss-evidence');
        $previousDirectory = $this->backupDirectory() . DIRECTORY_SEPARATOR . '.loss-evidence-before-restore-' . Str::uuid();
        File::ensureDirectoryExists(dirname($currentDirectory), 0700, true);

        $movedPrevious = false;
        if (is_dir($currentDirectory)) {
            if (! rename($currentDirectory, $previousDirectory)) {
                throw new RuntimeException('Folder bukti kehilangan saat ini tidak dapat diamankan sebelum restore.');
            }
            $movedPrevious = true;
        }

        if (! rename($stagedDirectory, $currentDirectory)) {
            if ($movedPrevious) {
                @rename($previousDirectory, $currentDirectory);
            }
            throw new RuntimeException('Folder bukti kehilangan dari backup tidak dapat dipulihkan.');
        }

        if ($movedPrevious && is_dir($previousDirectory)) {
            File::deleteDirectory($previousDirectory);
        }
    }

    private function findMysqlBinary(string $type): string
    {
        $configKey = $type === 'dump' ? 'mysql_dump_binary' : 'mysql_binary';
        $configured = config('orbitflow-backups.' . $configKey);
        if (is_string($configured) && $configured !== '') {
            if (is_file($configured)) {
                return $configured;
            }
            throw new RuntimeException('Executable database backup yang dikonfigurasi tidak ditemukan: ' . $configured);
        }

        $names = $type === 'dump' ? ['mysqldump', 'mariadb-dump'] : ['mysql', 'mariadb'];
        $finder = new ExecutableFinder();
        foreach ($names as $name) {
            $found = $finder->find($name);
            if ($found) {
                return $found;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $xamppHome = rtrim((string) (getenv('XAMPP_HOME') ?: 'C:\\xampp'), '\\/');
            foreach ($names as $name) {
                $candidate = $xamppHome . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $name . '.exe';
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException('Program database backup tidak ditemukan. Atur ORBITFLOW_MYSQL_DUMP_BINARY dan ORBITFLOW_MYSQL_BINARY di .env ke lokasi mysqldump.exe dan mysql.exe XAMPP.');
    }

    private function writeMysqlOptionsFile(string $workDirectory, array $connectionConfig): string
    {
        // Laravel names these keys `username` and `unix_socket`, while MySQL
        // option files expect `user` and `socket`. Map them explicitly. The
        // previous implementation looked for `user`, so mysqldump silently
        // fell back to the Windows account (for example, DELL).
        $optionMap = [
            'host' => 'host',
            'port' => 'port',
            'user' => 'username',
            'password' => 'password',
            'socket' => 'unix_socket',
        ];

        $username = trim((string) ($connectionConfig['username'] ?? ''));
        if ($username === '') {
            throw new RuntimeException('Username koneksi database Laravel kosong. Atur DB_USERNAME di .env agar backup memakai akun database yang benar.');
        }

        $lines = ['[client]'];
        foreach ($optionMap as $optionName => $configKey) {
            $value = $connectionConfig[$configKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $lines[] = $optionName . '=' . $this->escapeOptionFileValue((string) $value);
        }
        $lines[] = 'default-character-set=utf8mb4';

        $path = $workDirectory . DIRECTORY_SEPARATOR . '.orbitflow-mysql-client.cnf';
        if (file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX) === false) {
            throw new RuntimeException('File konfigurasi sementara database tidak dapat dibuat.');
        }
        @chmod($path, 0600);

        return $path;
    }

    private function escapeOptionFileValue(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace("\r", '\\r', $value);

        return '"' . $value . '"';
    }

    private function backupEncryptionPassword(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY belum diatur. Backup terenkripsi tidak dapat dibuat atau dibuka.');
        }

        return hash_hmac('sha256', 'orbitflow-backup-aes-256-v1', $key);
    }

    private function assertZipSupport(): void
    {
        if (! class_exists(ZipArchive::class) || ! defined('ZipArchive::EM_AES_256') || ! method_exists(ZipArchive::class, 'setEncryptionName')) {
            throw new RuntimeException('Ekstensi PHP zip dengan dukungan enkripsi AES-256 diperlukan. Aktifkan ekstensi zip pada PHP XAMPP lalu mulai ulang Apache/terminal.');
        }
    }

    private function backupDirectory(): string
    {
        return storage_path('app/private/' . trim((string) config('orbitflow-backups.directory', 'orbitflow-backups'), '/\\'));
    }

    private function isValidFilename(string $filename): bool
    {
        return basename($filename) === $filename && preg_match(self::BACKUP_FILENAME_PATTERN, $filename) === 1;
    }

    private function isSafeRelativeStoragePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:/', $path)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function absoluteDatabasePath(string $database): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $database) || str_starts_with($database, DIRECTORY_SEPARATOR)) {
            return $database;
        }

        return base_path($database);
    }
}
