<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrbitFlowBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_backup_management(): void
    {
        $this->get(route('admin.backups.index'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_backup_management(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA);

        $this->actingAs($student)
            ->get(route('admin.backups.index'))
            ->assertForbidden();

        $this->post(route('admin.backups.store'))->assertForbidden();
    }

    public function test_petugas_cannot_access_backup_management_or_downloads(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);

        $this->actingAs($petugas)
            ->get(route('admin.backups.index'))
            ->assertForbidden();

        $this->post(route('admin.backups.store'))->assertForbidden();
        $this->get(route('admin.backups.download', ['filename' => 'orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip']))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_private_backup_dashboard(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $service = Mockery::mock(OrbitFlowBackupService::class);
        $service->shouldReceive('listBackups')->once()->andReturn([
            [
                'filename' => 'orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip',
                'size_bytes' => 2048,
                'modified_at' => now()->timestamp,
            ],
        ]);
        $this->app->instance(OrbitFlowBackupService::class, $service);

        $this->actingAs($admin)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Cadangan & Pemulihan')
            ->assertSee('Buat backup sekarang')
            ->assertSee('orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip');
    }

    public function test_super_admin_can_create_a_backup_and_action_is_audited(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $service = Mockery::mock(OrbitFlowBackupService::class);
        $service->shouldReceive('createBackup')->once()->with('manual')->andReturn([
            'filename' => 'orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip',
            'path' => storage_path('app/private/orbitflow-backups/orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip'),
            'size_bytes' => 2048,
            'created_at' => '04/11/2026 01:00:00 WIB',
            'database_driver' => 'mysql',
            'storage_file_count' => 2,
            'private_evidence_file_count' => 1,
        ]);
        $this->app->instance(OrbitFlowBackupService::class, $service);

        $this->actingAs($admin)
            ->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $admin->id,
            'category' => 'backup',
            'event_key' => 'backup.created',
        ]);
    }

    public function test_super_admin_can_verify_a_backup_and_action_is_audited(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $filename = 'orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip';
        $service = Mockery::mock(OrbitFlowBackupService::class);
        $service->shouldReceive('verifyBackup')->once()->with($filename)->andReturn([
            'filename' => $filename,
            'created_at_wib' => '04/11/2026 01:00:00 WIB',
            'database_driver' => 'mysql',
            'database_name' => 'orbitflow',
            'database_size_bytes' => 1000,
            'storage_file_count' => 0,
            'storage_size_bytes' => 0,
            'private_evidence_file_count' => 0,
            'private_evidence_size_bytes' => 0,
            'verified' => true,
        ]);
        $this->app->instance(OrbitFlowBackupService::class, $service);

        $this->actingAs($admin)
            ->post(route('admin.backups.verify', ['filename' => $filename]))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $admin->id,
            'event_key' => 'backup.verified',
        ]);
    }

    public function test_super_admin_can_download_a_backup_through_private_route(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $filename = 'orbitflow-backup-manual-20261104-010000-a1b2c3d4.zip';
        $temporaryFile = tempnam(sys_get_temp_dir(), 'orbitflow-backup-test-');
        file_put_contents($temporaryFile, 'encrypted-test-archive');

        try {
            $service = Mockery::mock(OrbitFlowBackupService::class);
            $service->shouldReceive('absolutePath')->once()->with($filename)->andReturn($temporaryFile);
            $this->app->instance(OrbitFlowBackupService::class, $service);

            $this->actingAs($admin)
                ->get(route('admin.backups.download', ['filename' => $filename]))
                ->assertOk()
                ->assertDownload($filename);

            $this->assertDatabaseHas('activity_logs', [
                'actor_user_id' => $admin->id,
                'event_key' => 'backup.downloaded',
            ]);
        } finally {
            @unlink($temporaryFile);
        }
    }

    public function test_backup_service_rejects_path_traversal_filenames(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(OrbitFlowBackupService::class)->absolutePath('../../.env');
    }

    public function test_restore_is_not_exposed_as_a_web_route(): void
    {
        $this->assertFalse(Route::has('admin.backups.restore'));
    }



    public function test_backup_collector_includes_only_private_loss_evidence_and_keeps_other_private_files_excluded(): void
    {
        $evidenceDirectory = storage_path('app/private/loss-evidence');
        $evidenceFilename = 'backup-test-' . uniqid('', true) . '.jpg';
        $evidencePath = $evidenceDirectory . DIRECTORY_SEPARATOR . $evidenceFilename;
        $otherPrivatePath = storage_path('app/private/temporary-preview-' . uniqid('', true) . '.csv');

        \Illuminate\Support\Facades\File::ensureDirectoryExists($evidenceDirectory, 0700, true);
        file_put_contents($evidencePath, 'private loss evidence');
        file_put_contents($otherPrivatePath, 'temporary csv preview must stay excluded');

        try {
            $service = app(OrbitFlowBackupService::class);
            $method = new \ReflectionMethod(OrbitFlowBackupService::class, 'collectPrivateLossEvidenceFiles');
            $result = $method->invoke($service);

            $matchingEvidence = array_values(array_filter(
                $result['manifest'],
                fn (array $file) => $file['path'] === $evidenceFilename
            ));
            $this->assertCount(1, $matchingEvidence);
            $this->assertSame(hash('sha256', 'private loss evidence'), $matchingEvidence[0]['sha256']);
            $this->assertSame(strlen('private loss evidence'), $matchingEvidence[0]['size_bytes']);
            $this->assertArrayHasKey($evidenceFilename, $result['absolute_files']);
            $this->assertArrayNotHasKey('../temporary-preview.csv', $result['absolute_files']);
            $this->assertStringNotContainsString('temporary-preview', implode(' ', array_keys($result['absolute_files'])));
        } finally {
            @unlink($evidencePath);
            @unlink($otherPrivatePath);
        }
    }

    public function test_mysql_options_file_maps_laravel_username_and_socket_configuration(): void
    {
        $directory = storage_path('framework/testing/mysql-options-' . uniqid());
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        try {
            $service = app(OrbitFlowBackupService::class);
            $method = new \ReflectionMethod(OrbitFlowBackupService::class, 'writeMysqlOptionsFile');
            $path = $method->invoke($service, $directory, [
                'host' => '127.0.0.1',
                'port' => 3306,
                'username' => 'orbitflow_test_user',
                'password' => 'sample-secret-value',
                'unix_socket' => 'C:/mysql/mysql.sock',
            ]);

            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringContainsString('user="orbitflow_test_user"', $contents);
            $this->assertStringContainsString('password="sample-secret-value"', $contents);
            $this->assertStringContainsString('socket="C:/mysql/mysql.sock"', $contents);
            $this->assertStringNotContainsString('DELL', $contents);
        } finally {
            if (is_dir($directory)) {
                \Illuminate\Support\Facades\File::deleteDirectory($directory);
            }
        }
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Pengguna Uji Backup',
            'email' => uniqid($role . '-backup-', true) . '@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
