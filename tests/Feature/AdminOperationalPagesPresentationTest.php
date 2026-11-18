<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\OrbitFlowBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminOperationalPagesPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_has_responsive_cards_and_keeps_filtered_detail_navigation(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN, 'activity-ui-admin@example.test');
        $log = $this->makeLog($admin);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index', ['category' => 'security']))
            ->assertOk()
            ->assertSee('activity-log-desktop', false)
            ->assertSee('activity-log-cards', false)
            ->assertSee('activity-log-card', false)
            ->assertSee('Login berhasil')
            ->assertSee(route('admin.activity-logs.show', $log), false);
    }

    public function test_activity_detail_explains_before_and_after_audit_data(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN, 'activity-detail-admin@example.test');
        $log = $this->makeLog($admin, [
            'before_data' => ['status' => 'inactive'],
            'after_data' => ['status' => 'active'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.show', $log))
            ->assertOk()
            ->assertSee('Data sebelum perubahan')
            ->assertSee('Data setelah perubahan')
            ->assertSee('inactive')
            ->assertSee('active')
            ->assertSee('activity-detail-panel', false);
    }

    public function test_backup_page_has_mobile_cards_and_keeps_restore_cli_only(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN, 'backup-ui-admin@example.test');
        $filename = 'orbitflow-backup-manual-20261105-010000-a1b2c3d4.zip';
        $service = Mockery::mock(OrbitFlowBackupService::class);
        $service->shouldReceive('listBackups')->once()->andReturn([
            [
                'filename' => $filename,
                'size_bytes' => 2048,
                'modified_at' => now()->timestamp,
            ],
        ]);
        $this->app->instance(OrbitFlowBackupService::class, $service);

        $this->actingAs($admin)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('backup-desktop-list', false)
            ->assertSee('backup-mobile-list', false)
            ->assertSee('backup-mobile-card', false)
            ->assertSee('Verifikasi integritas')
            ->assertSee('Pemulihan melalui terminal')
            ->assertDontSee('name="restore"', false);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => 'Pengguna Uji',
            'email' => $email,
            'password' => 'Secure-Test-Password-2026!',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeLog(User $actor, array $overrides = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'actor_role' => $actor->role,
            'category' => 'security',
            'event_key' => 'auth.login.succeeded',
            'summary' => 'Pengguna berhasil login.',
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => 'Akun admin',
            'before_data' => null,
            'after_data' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ], $overrides));
    }
}
