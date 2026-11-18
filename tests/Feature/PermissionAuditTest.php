<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_guests_are_redirected_from_student_and_admin_workspaces(): void
    {
        $this->get(route('siswa.dashboard'))->assertRedirect(route('login'));
        $this->get(route('borrowing.history'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.students.index'))->assertRedirect(route('login'));
        $this->get(route('admin.staff.index'))->assertRedirect(route('login'));
        $this->get(route('admin.activity-logs.index'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_view_another_students_borrowing_status_success_or_history(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA, 'owner.student@example.test');
        $otherStudent = $this->makeUser(User::ROLE_SISWA, 'other.student@example.test');
        $ownBorrowing = $this->makeBorrowing($student, 'PRIVATE-OWN-PURPOSE');
        $otherBorrowing = $this->makeBorrowing($otherStudent, 'PRIVATE-OTHER-STUDENT-PURPOSE');
        $ownReference = 'OF-' . str_pad((string) $ownBorrowing->id, 5, '0', STR_PAD_LEFT);
        $otherReference = 'OF-' . str_pad((string) $otherBorrowing->id, 5, '0', STR_PAD_LEFT);

        $this->actingAs($student)
            ->get(route('borrowing.status', ['id' => $otherBorrowing->id]))
            ->assertNotFound();

        $this->get(route('borrowing.success', $otherBorrowing))->assertForbidden();

        $history = $this->get(route('borrowing.history'));
        $history->assertOk()
            ->assertSee($ownReference)
            ->assertDontSee($otherReference);

        $this->assertDatabaseHas('borrowings', [
            'id' => $ownBorrowing->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_student_cannot_submit_a_borrowing_on_behalf_of_another_user(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA, 'request.owner@example.test');
        $otherStudent = $this->makeUser(User::ROLE_SISWA, 'request.other@example.test');
        $equipment = Equipment::create([
            'name' => 'Kamera Uji Kepemilikan',
            'category' => 'Multimedia',
            'description' => 'Peralatan untuk pengujian hak akses.',
        ]);
        EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'CAM-OWNERSHIP-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $this->actingAs($student)
            ->post(route('borrowing.store'), [
                'user_id' => $otherStudent->id,
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
                'purpose' => 'Pengujian kepemilikan akun.',
                'items' => [
                    ['equipment_id' => $equipment->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('borrowings', [
            'user_id' => $student->id,
            'purpose' => 'Pengujian kepemilikan akun.',
        ]);
        $this->assertDatabaseMissing('borrowings', [
            'user_id' => $otherStudent->id,
            'purpose' => 'Pengujian kepemilikan akun.',
        ]);
    }

    public function test_petugas_cannot_access_any_staff_management_action(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS, 'petugas.restricted@example.test');
        $targetStaff = $this->makeUser(User::ROLE_PETUGAS, 'target.staff@example.test');

        $this->actingAs($petugas)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
        $this->get(route('admin.staff.create'))->assertForbidden();
        $this->post(route('admin.staff.store'), [
            'name' => 'Staf Tidak Sah',
            'email' => 'unauthorized.staff@example.test',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'long-test-password',
            'password_confirmation' => 'long-test-password',
        ])->assertForbidden();
        $this->get(route('admin.staff.edit', $targetStaff))->assertForbidden();
        $this->put(route('admin.staff.update', $targetStaff), [
            'name' => 'Diubah Tanpa Izin',
            'email' => 'changed.staff@example.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertForbidden();
        $this->patch(route('admin.staff.toggle-status', $targetStaff))->assertForbidden();
        $this->post(route('admin.staff.password-reset-link', $targetStaff))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized.staff@example.test']);
        $this->assertDatabaseHas('users', [
            'id' => $targetStaff->id,
            'email' => 'target.staff@example.test',
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    public function test_student_import_batches_are_only_accessible_to_the_creating_administrator(): void
    {
        $owner = $this->makeUser(User::ROLE_PETUGAS, 'import.owner@example.test');
        $otherPetugas = $this->makeUser(User::ROLE_PETUGAS, 'import.other@example.test');
        $batch = $this->uploadCsv($owner, "nis,name,email,class_name\n90001,Siswa Uji,imported.one@example.test,XII RPL 1\n");

        $this->actingAs($otherPetugas)
            ->get(route('admin.students.import.batch', ['batch' => $batch]))
            ->assertForbidden();
        $this->get(route('admin.students.import.batch.errors', ['batch' => $batch]))
            ->assertForbidden();
        $this->post(route('admin.students.import.batch.process', ['batch' => $batch]))
            ->assertForbidden();
        $this->post(route('admin.students.import.batch.cancel', ['batch' => $batch]))
            ->assertForbidden();

        $this->assertTrue(Storage::disk('local')->exists('student-imports/' . $batch . '.json'));

        $this->actingAs($owner)
            ->get(route('admin.students.import.batch', ['batch' => $batch]))
            ->assertOk();
    }

    public function test_student_import_result_is_hidden_after_switching_to_another_administrator(): void
    {
        $owner = $this->makeUser(User::ROLE_PETUGAS, 'result.owner@example.test');
        $otherPetugas = $this->makeUser(User::ROLE_PETUGAS, 'result.other@example.test');
        $batch = $this->uploadCsv($owner, "nis,name,email,class_name\n90002,Siswa Hasil,result.student@example.test,XII RPL 2\n");

        $this->actingAs($owner)
            ->post(route('admin.students.import.batch.process', ['batch' => $batch]))
            ->assertRedirect(route('admin.students.import.result'));

        $this->assertSame($owner->id, session('student_import_result.created_by'));
        $this->assertSame($owner->id, session('student_import_activation_export.created_by'));

        $this->actingAs($otherPetugas)
            ->get(route('admin.students.import.result'))
            ->assertRedirect(route('admin.students.import.index'));

        $this->assertNull(session('student_import_result'));
        $this->assertNull(session('student_import_activation_export'));
    }

    public function test_activation_link_export_cannot_be_downloaded_by_a_different_administrator(): void
    {
        $owner = $this->makeUser(User::ROLE_PETUGAS, 'export.owner@example.test');
        $otherPetugas = $this->makeUser(User::ROLE_PETUGAS, 'export.other@example.test');
        $batch = $this->uploadCsv($owner, "nis,name,email,class_name\n90003,Siswa Export,export.student@example.test,XII RPL 3\n");

        $this->actingAs($owner)
            ->post(route('admin.students.import.batch.process', ['batch' => $batch]))
            ->assertRedirect(route('admin.students.import.result'));

        $this->actingAs($otherPetugas)
            ->from(route('admin.students.import.result'))
            ->post(route('admin.students.import.result.download'))
            ->assertRedirect(route('admin.students.import.result'))
            ->assertSessionHasErrors('activation_export');

        $this->assertDatabaseHas('users', [
            'nis' => '90003',
            'email' => 'export.student@example.test',
            'is_active' => false,
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => 'Pengguna Uji',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeBorrowing(User $owner, string $purpose): Borrowing
    {
        return Borrowing::create([
            'user_id' => $owner->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'purpose' => $purpose,
            'status' => 'Pending',
        ]);
    }

    private function uploadCsv(User $owner, string $contents): string
    {
        $response = $this->actingAs($owner)->post(route('admin.students.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('permission-audit.csv', $contents),
        ]);
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertMatchesRegularExpression('~/admin/siswa/impor/([a-f0-9-]+)/pratinjau$~', $location);
        preg_match('~/admin/siswa/impor/([a-f0-9-]+)/pratinjau$~', $location, $matches);

        return $matches[1];
    }
}
