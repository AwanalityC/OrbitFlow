<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_can_create_a_student_account_with_school_identity(): void
    {
        $petugas = $this->makeUser('petugas');

        $this->actingAs($petugas)
            ->post(route('admin.students.store'), [
                'name' => 'Nadia Pratama',
                'email' => 'nadia.pratama@example.test',
                'nis' => '202611001',
                'class_name' => 'XII RPL 2',
                'password' => 'student-pass-123',
                'password_confirmation' => 'student-pass-123',
            ])
            ->assertRedirect(route('admin.students.index'));

        $student = User::where('email', 'nadia.pratama@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_SISWA, $student->role);
        $this->assertSame('202611001', $student->nis);
        $this->assertSame('XII RPL 2', $student->class_name);
        $this->assertTrue($student->is_active);
        $this->assertTrue(Hash::check('student-pass-123', $student->password));
    }

    public function test_student_creation_cannot_assign_an_administrative_role(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.students.store'), [
                'name' => 'Siswa Aman',
                'email' => 'siswa.aman@example.test',
                'nis' => '202611002',
                'class_name' => 'XII RPL 1',
                'password' => 'student-pass-123',
                'password_confirmation' => 'student-pass-123',
                'role' => User::ROLE_SUPER_ADMIN,
            ])
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'siswa.aman@example.test',
            'role' => User::ROLE_SISWA,
        ]);
    }

    public function test_student_creation_rejects_duplicate_nis_and_email(): void
    {
        $petugas = $this->makeUser('petugas');
        $this->makeUser('siswa', [
            'email' => 'existing.student@example.test',
            'nis' => '202611003',
        ]);

        $this->actingAs($petugas)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => 'Duplikat',
                'email' => 'existing.student@example.test',
                'nis' => '202611003',
                'class_name' => 'XII RPL 1',
                'password' => 'student-pass-123',
                'password_confirmation' => 'student-pass-123',
            ])
            ->assertRedirect(route('admin.students.create'))
            ->assertSessionHasErrors(['email', 'nis']);
    }

    public function test_petugas_can_update_student_identity_without_changing_borrowing_history(): void
    {
        $petugas = $this->makeUser('petugas');
        $student = $this->makeUser('siswa', [
            'name' => 'Siswa Lama',
            'email' => 'siswa.lama@example.test',
            'nis' => '202611004',
            'class_name' => 'XI RPL 1',
        ]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'purpose' => 'Keperluan tugas sekolah.',
            'status' => 'Pending',
        ]);

        $this->actingAs($petugas)
            ->put(route('admin.students.update', $student), [
                'name' => 'Siswa Diperbarui',
                'email' => 'siswa.baru@example.test',
                'nis' => '202611104',
                'class_name' => 'XII RPL 2',
            ])
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Siswa Diperbarui',
            'email' => 'siswa.baru@example.test',
            'nis' => '202611104',
            'class_name' => 'XII RPL 2',
        ]);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_petugas_can_deactivate_and_reactivate_student_without_deleting_history(): void
    {
        $petugas = $this->makeUser('petugas');
        $student = $this->makeUser('siswa');
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'purpose' => 'Riwayat harus tetap tersimpan.',
            'status' => 'Returned',
        ]);

        $this->actingAs($petugas)
            ->patch(route('admin.students.toggle-status', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'user_id' => $student->id]);

        $this->patch(route('admin.students.toggle-status', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => true]);
    }

    public function test_student_cannot_access_account_management(): void
    {
        $student = $this->makeUser('siswa');

        $this->actingAs($student)
            ->get(route('admin.students.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    public function test_petugas_can_manage_students_but_cannot_manage_staff_accounts(): void
    {
        $petugas = $this->makeUser('petugas');

        $this->actingAs($petugas)
            ->get(route('admin.students.index'))
            ->assertOk();

        $this->get(route('admin.staff.index'))->assertForbidden();
        $this->get(route('admin.staff.create'))->assertForbidden();
        $this->post(route('admin.staff.store'), [
            'name' => 'Tidak Diizinkan',
            'email' => 'tidak.diizinkan@example.test',
            'role' => User::ROLE_PETUGAS,
            'password' => 'staff-pass-123',
            'password_confirmation' => 'staff-pass-123',
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_petugas_and_super_admin_accounts(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.staff.store'), [
                'name' => 'Petugas Baru',
                'email' => 'petugas.baru@example.test',
                'role' => User::ROLE_PETUGAS,
                'password' => 'staff-pass-123',
                'password_confirmation' => 'staff-pass-123',
            ])
            ->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'petugas.baru@example.test',
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);

        $this->post(route('admin.staff.store'), [
            'name' => 'Super Admin Kedua',
            'email' => 'superadmin.kedua@example.test',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'staff-pass-456',
            'password_confirmation' => 'staff-pass-456',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'superadmin.kedua@example.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_super_admin_can_change_staff_role_but_cannot_remove_own_super_admin_role(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $petugas = $this->makeUser(User::ROLE_PETUGAS);

        $this->actingAs($superAdmin)
            ->put(route('admin.staff.update', $petugas), [
                'name' => $petugas->name,
                'email' => $petugas->email,
                'role' => User::ROLE_SUPER_ADMIN,
            ])
            ->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('users', [
            'id' => $petugas->id,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->put(route('admin.staff.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => User::ROLE_PETUGAS,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_super_admin_cannot_deactivate_own_account(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->patch(route('admin.staff.toggle-status', $superAdmin))
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Uji',
            'email' => uniqid($role . '-', true) . '@example.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }
}
