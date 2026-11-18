<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_admin_role_is_treated_as_super_admin_for_compatibility(): void
    {
        // Legacy admin rows created by test helpers remain accepted by the
        // compatibility middleware; the new migration normalizes real rows.
        $admin = $this->makeUser('admin');

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('Super Admin', $admin->role_label);
    }

    public function test_super_admin_and_petugas_can_access_the_admin_workspace(): void
    {
        foreach (['super_admin', 'petugas'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get(route('admin.dashboard'))
                ->assertOk();
        }
    }

    public function test_student_cannot_access_the_admin_workspace(): void
    {
        $student = $this->makeUser('siswa');

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_redirects_each_role_to_the_correct_workspace(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $this->actingAs($superAdmin)
            ->get('/dashboard')
            ->assertRedirect('/admin/dashboard');

        auth()->logout();

        $petugas = $this->makeUser('petugas');
        $this->actingAs($petugas)
            ->get('/dashboard')
            ->assertRedirect('/admin/dashboard');

        auth()->logout();

        $student = $this->makeUser('siswa');
        $this->actingAs($student)
            ->get('/dashboard')
            ->assertRedirect('/siswa/dashboard');
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = $this->makeUser('siswa', [
            'email' => 'inactive.student@example.test',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_authenticated_account_is_signed_out_from_protected_pages(): void
    {
        $user = $this->makeUser('siswa', ['is_active' => false]);

        $this->actingAs($user)
            ->get(route('siswa.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_new_accounts_are_active_by_default_and_store_student_identity(): void
    {
        $student = $this->makeUser('siswa', [
            'nis' => '202600123',
            'class_name' => 'XII RPL 1',
        ]);

        // New Eloquent instances should reflect the active-by-default policy
        // before and after their database record is reloaded.
        $this->assertTrue($student->is_active);
        $this->assertTrue($student->fresh()->is_active);
        $this->assertSame('202600123', $student->nis);
        $this->assertSame('XII RPL 1', $student->class_name);
    }

    public function test_student_directory_can_search_by_nis_and_shows_account_identity(): void
    {
        $admin = $this->makeUser('super_admin');
        $student = $this->makeUser('siswa', [
            'name' => 'Nadia Pratama',
            'email' => 'nadia@example.test',
            'nis' => '202600456',
            'class_name' => 'XII RPL 2',
        ]);
        $this->makeUser('siswa', [
            'name' => 'Rafi Contoh',
            'email' => 'rafi@example.test',
            'nis' => '202600789',
            'class_name' => 'XII RPL 1',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.index', ['q' => '202600456']))
            ->assertOk()
            ->assertSee($student->email)
            ->assertSee('202600456')
            ->assertSee('XII RPL 2')
            ->assertDontSee('Rafi Contoh');
    }

    public function test_nis_must_be_unique_when_present(): void
    {
        $this->makeUser('siswa', ['nis' => '202600999']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeUser('siswa', ['nis' => '202600999']);
    }

    /** @param array<string, mixed> $attributes */
    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Uji',
            'email' => uniqid($role . '-', true) . '@example.test',
            'password' => 'password123',
            'role' => $role,
        ], $attributes));
    }
}
