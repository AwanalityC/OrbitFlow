<?php

namespace Tests\Feature;

use App\Models\StudentAccountActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserManagementPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_student_directory_has_mobile_cards_and_direct_status_shortcuts(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS, 'petugas.directory@example.test');
        $active = $this->makeUser(User::ROLE_SISWA, 'siswa.aktif@example.test', [
            'name' => 'Siswa Aktif', 'nis' => '81001', 'class_name' => 'XII RPL 1',
        ]);
        $pending = $this->makeUser(User::ROLE_SISWA, 'siswa.pending@example.test', [
            'name' => 'Siswa Menunggu', 'nis' => '81002', 'class_name' => 'XII RPL 2', 'is_active' => false,
        ]);
        StudentAccountActivation::create([
            'user_id' => $pending->id,
            'token_hash' => hash('sha256', str_repeat('P', 64)),
            'expires_at' => now()->addDay(),
            'created_by' => $petugas->id,
        ]);

        $this->actingAs($petugas)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('admin-account-cards', false)
            ->assertSee('Siswa Aktif')
            ->assertSee('Siswa Menunggu')
            ->assertSee('81001')
            ->assertSee('XII RPL 2')
            ->assertSee(route('admin.students.index', ['status' => 'active']), false)
            ->assertSee(route('admin.students.index', ['status' => 'pending']), false)
            ->assertSee('Lihat antrean aktivasi');
    }

    public function test_super_admin_staff_directory_has_mobile_cards_and_role_shortcuts(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN, 'superadmin.directory@example.test');
        $petugas = $this->makeUser(User::ROLE_PETUGAS, 'petugas.card@example.test', [
            'name' => 'Petugas Kartu',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('admin-account-cards', false)
            ->assertSee('Petugas Kartu')
            ->assertSee('Petugas Inventaris')
            ->assertSee('Akun Anda')
            ->assertSee(str_replace('&', '&amp;', route('admin.staff.index', ['role' => 'super_admin', 'status' => 'active'])), false)
            ->assertSee(route('admin.staff.index', ['status' => 'inactive']), false);
    }

    public function test_account_forms_show_field_level_validation_feedback(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN, 'superadmin.forms@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('aria-invalid="false"', false)
            ->assertSee('NIS harus unik dan berasal dari data resmi sekolah.');

        $this->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'nis' => '',
                'class_name' => '',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('admin.students.create'));

        $this->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('class="admin-field-error" role="alert"', false);

        $this->get(route('admin.staff.create'))
            ->assertOk()
            ->assertSee('Peran administratif')
            ->assertSee('Super Admin memiliki akses penuh', false);

        $this->from(route('admin.staff.create'))
            ->post(route('admin.staff.store'), [
                'name' => '',
                'email' => 'invalid-email',
                'role' => 'student',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('admin.staff.create'));

        $this->get(route('admin.staff.create'))
            ->assertOk()
            ->assertSee('class="admin-field-error" role="alert"', false);
    }

    public function test_csv_preview_has_mobile_cards_for_valid_and_invalid_rows(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS, 'petugas.csvpreview@example.test');
        $csv = "nis,name,email,class_name\n"
            . "81011,Siswa Valid,valid.preview@example.test,XII RPL 1\n"
            . ",Nama Tidak Lengkap,invalid.preview@example.test,XII RPL 2\n";

        $upload = $this->actingAs($petugas)->post(route('admin.students.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
        ]);
        $upload->assertRedirect();
        $location = (string) $upload->headers->get('Location');
        $this->assertMatchesRegularExpression('~/admin/siswa/impor/([a-f0-9-]+)/pratinjau$~', $location);

        $preview = $this->get($location);
        $preview->assertOk()
            ->assertSee('admin-import-preview-cards', false)
            ->assertSee('Siswa Valid')
            ->assertSee('Nama Tidak Lengkap')
            ->assertSee('Perlu diperbaiki')
            ->assertSee('invalid.preview@example.test');
    }

    private function makeUser(string $role, string $email, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Uji',
            'email' => $email,
            'password' => 'secure-test-password',
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }
}
