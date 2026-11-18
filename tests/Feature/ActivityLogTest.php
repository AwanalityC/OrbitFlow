<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_all_activity_logs_and_filter_them(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'superadmin@example.test');
        $petugas = $this->makeUser('petugas', 'petugas@example.test');

        $this->makeLog($superAdmin, 'Akun siswa Nadia dibuat.', 'accounts');
        $inventoryLog = $this->makeLog($petugas, 'Kondisi unit CAMERA-001 diperbarui.', 'inventory', 'inventory.condition_updated');

        $this->actingAs($superAdmin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('Akun siswa Nadia dibuat.')
            ->assertSee('Kondisi unit CAMERA-001 diperbarui.');

        $this->get(route('admin.activity-logs.index', ['category' => 'inventory', 'q' => 'CAMERA-001']))
            ->assertOk()
            ->assertSee('Kondisi unit CAMERA-001 diperbarui.')
            ->assertDontSee('Akun siswa Nadia dibuat.');

        $this->get(route('admin.activity-logs.show', $inventoryLog))->assertOk();
    }

    public function test_petugas_can_only_view_their_own_activity_logs(): void
    {
        $petugas = $this->makeUser('petugas', 'petugas-own@example.test');
        $otherPetugas = $this->makeUser('petugas', 'petugas-other@example.test');

        $ownLog = $this->makeLog($petugas, 'Tindakan milik petugas sendiri.', 'inventory');
        $otherLog = $this->makeLog($otherPetugas, 'Tindakan milik petugas lain.', 'accounts');

        $this->actingAs($petugas)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('Tindakan milik petugas sendiri.')
            ->assertDontSee('Tindakan milik petugas lain.');

        $this->get(route('admin.activity-logs.show', $ownLog))->assertOk();
        $this->get(route('admin.activity-logs.show', $otherLog))->assertForbidden();
    }

    public function test_student_cannot_access_activity_logs(): void
    {
        $student = $this->makeUser('siswa', 'student-log@example.test');
        $this->actingAs($student)->get(route('admin.activity-logs.index'))->assertForbidden();
    }

    public function test_student_account_creation_is_logged_without_password_data(): void
    {
        $petugas = $this->makeUser('petugas', 'petugas-create@example.test');

        $this->actingAs($petugas)
            ->post(route('admin.students.store'), [
                'name' => 'Siswa Audit',
                'email' => 'siswa.audit@example.test',
                'nis' => '202611901',
                'class_name' => 'XII RPL 2',
                'password' => 'student-secret-123',
                'password_confirmation' => 'student-secret-123',
            ])
            ->assertRedirect(route('admin.students.index'));

        $log = ActivityLog::where('event_key', 'student.created')->firstOrFail();
        $this->assertSame($petugas->id, $log->actor_user_id);
        $this->assertSame('Siswa Audit · NIS 202611901', $log->subject_label);
        $this->assertSame('202611901', $log->after_data['nis']);
        $this->assertArrayNotHasKey('password', $log->after_data);
        $this->assertStringNotContainsString('student-secret-123', json_encode($log->getAttributes()));
    }

    public function test_inventory_condition_change_logs_before_and_after_values(): void
    {
        $petugas = $this->makeUser('petugas', 'petugas-inventory@example.test');
        $equipment = Equipment::create(['name' => 'Tripod Audit', 'category' => 'Multimedia']);
        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'TRIPOD-AUDIT-001',
            'condition' => 'Rusak Berat',
            'status' => 'Tidak Tersedia',
        ]);

        $this->actingAs($petugas)
            ->post(route('admin.equipment-units.update-condition', $unit), ['condition' => 'Baik'])
            ->assertRedirect(route('admin.equipment-units.index'));

        $log = ActivityLog::where('event_key', 'inventory.condition_updated')->firstOrFail();
        $this->assertSame('Rusak Berat', $log->before_data['condition']);
        $this->assertSame('Tidak Tersedia', $log->before_data['status']);
        $this->assertSame('Baik', $log->after_data['condition']);
        $this->assertSame('Tersedia', $log->after_data['status']);
    }

    public function test_borrowing_approval_is_recorded_with_its_reference(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('super_admin', 'approval-admin@example.test');
        $student = $this->makeUser('siswa', 'approval-student@example.test');
        $equipment = Equipment::create(['name' => 'Kamera Audit', 'category' => 'Multimedia']);
        EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'CAM-AUDIT-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'purpose' => 'Uji catatan persetujuan.',
            'status' => 'Pending',
        ]);
        $borrowing->items()->create(['equipment_id' => $equipment->id, 'quantity' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $borrowing), [
                'handover_photos' => [$equipment->id => UploadedFile::fake()->image('handover-audit.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $log = ActivityLog::where('event_key', 'borrowing.approved')->firstOrFail();
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('Borrowed', $log->after_data['status']);
        $this->assertStringContainsString('OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT), $log->subject_label);
    }

    public function test_activity_logger_removes_password_and_token_fields_recursively(): void
    {
        $actor = $this->makeUser('super_admin', 'superadmin-safe@example.test');

        $this->actingAs($actor);
        $log = ActivityLogger::record(
            'security',
            'security.sanitizer_test',
            'Uji penyaringan data sensitif.',
            null,
            [
                'name' => 'Akun Aman',
                'password' => 'should-not-be-recorded',
                'nested' => ['token' => 'should-not-be-recorded', 'status' => 'active'],
            ],
            ['activation_link' => 'should-not-be-recorded', 'count' => 2]
        );

        $this->assertNotNull($log);
        $this->assertSame(['name' => 'Akun Aman', 'nested' => ['status' => 'active']], $log->before_data);
        $this->assertSame(['count' => 2], $log->after_data);
        $this->assertStringNotContainsString('should-not-be-recorded', json_encode($log->getAttributes()));
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst(str_replace(['.', '-', '@example.test'], ' ', $email)),
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeLog(User $actor, string $summary, string $category, string $eventKey = 'test.recorded'): ActivityLog
    {
        return ActivityLog::create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'actor_role' => $actor->role,
            'category' => $category,
            'event_key' => $eventKey,
            'summary' => $summary,
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'before_data' => null,
            'after_data' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);
    }
}
