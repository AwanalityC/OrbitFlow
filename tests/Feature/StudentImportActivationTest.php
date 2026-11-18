<?php

namespace Tests\Feature;

use App\Models\StudentAccountActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_petugas_can_open_import_page_and_download_csv_template(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);

        $this->actingAs($petugas)
            ->get(route('admin.students.import.index'))
            ->assertOk()
            ->assertSee('Impor data siswa')
            ->assertSee('nis, name, email, class_name');

        $template = $this->get(route('admin.students.import.template'));
        $template->assertOk();
        $this->assertStringContainsString('nis,name,email,class_name', $template->streamedContent());
    }

    public function test_student_cannot_preview_or_import_csv(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA);

        $this->actingAs($student)
            ->get(route('admin.students.import.index'))
            ->assertForbidden();

        $this->post(route('admin.students.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', "nis,name,email,class_name\n10001,Siswa,siswa@example.test,XII RPL 1\n"),
        ])->assertForbidden();
    }

    public function test_preview_marks_existing_and_in_file_duplicates_as_invalid(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $this->makeUser(User::ROLE_SISWA, [
            'name' => 'Siswa Lama',
            'email' => 'lama@example.test',
            'nis' => '10001',
        ]);

        $csv = "nis,name,email,class_name\n"
            . "10002,Siswa Baru,baru@example.test,XII RPL 1\n"
            . "10001,Siswa Duplikat,duplikat@example.test,XII RPL 2\n"
            . "10003,Siswa Email Duplikat,lama@example.test,XII RPL 3\n"
            . "10002,Siswa Duplikat Dalam File,baru2@example.test,XII RPL 4\n";

        $batch = $this->uploadCsv($petugas, $csv);

        $this->actingAs($petugas)
            ->get(route('admin.students.import.batch', ['batch' => $batch]))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('3')
            ->assertSee('NIS sudah terdaftar di OrbitFlow.')
            ->assertSee('Email sudah digunakan oleh akun lain.')
            ->assertSee('NIS duplikat di dalam file CSV.');

        $errorReport = $this->get(route('admin.students.import.batch.errors', ['batch' => $batch]));
        $errorReport->assertOk();
        $errorCsv = $errorReport->streamedContent();
        $this->assertStringContainsString('NIS sudah terdaftar di OrbitFlow.', $errorCsv);
        $this->assertStringContainsString('Email sudah digunakan oleh akun lain.', $errorCsv);
    }

    public function test_csv_with_missing_required_headers_is_rejected(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);

        $this->actingAs($petugas)
            ->from(route('admin.students.import.index'))
            ->post(route('admin.students.import.preview'), [
                'csv_file' => UploadedFile::fake()->createWithContent('wrong.csv', "name,email\nSiswa,siswa@example.test\n"),
            ])
            ->assertRedirect(route('admin.students.import.index'))
            ->assertSessionHasErrors('csv_file');
    }

    public function test_import_creates_inactive_student_and_downloads_one_time_activation_link(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $batch = $this->uploadCsv($petugas, "nis,name,email,class_name\n10011,Nadia Import,nadia.import@example.test,XII RPL 2\n");

        $this->actingAs($petugas)
            ->post(route('admin.students.import.batch.process', ['batch' => $batch]))
            ->assertRedirect(route('admin.students.import.result'));

        $student = User::where('email', 'nadia.import@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_SISWA, $student->role);
        $this->assertFalse($student->is_active);
        $this->assertSame('10011', $student->nis);
        $this->assertSame('XII RPL 2', $student->class_name);
        $this->assertNotSame('', $student->password);
        $this->assertFalse(Hash::check('password123', $student->password));
        $activation = StudentAccountActivation::where('user_id', $student->id)->firstOrFail();
        $this->assertNull($activation->used_at);
        $this->assertTrue($activation->expires_at->isFuture());

        $resultPage = $this->get(route('admin.students.import.result'));
        $resultPage->assertOk()
            ->assertSee('1')
            ->assertSee('Unduh tautan aktivasi CSV');

        $download = $this->post(route('admin.students.import.result.download'));
        $download->assertOk();
        $exportRows = $this->parseCsv($download->streamedContent());
        $this->from(route('admin.students.import.result'))
            ->post(route('admin.students.import.result.download'))
            ->assertRedirect(route('admin.students.import.result'))
            ->assertSessionHasErrors('activation_export');
        $this->assertCount(2, $exportRows);
        $this->assertSame('10011', $exportRows[1][0]);
        $activationUrl = $exportRows[1][3];
        $token = basename((string) parse_url($activationUrl, PHP_URL_PATH));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
        $this->assertSame(hash('sha256', $token), $activation->fresh()->token_hash);

        $activationPath = (string) parse_url($activationUrl, PHP_URL_PATH);
        $this->get($activationPath)->assertOk()->assertSee('Buat kata sandi akun');

        $this->post(route('student.activation.activate', ['token' => $token]), [
            'password' => 'secure-student-password',
            'password_confirmation' => 'secure-student-password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'is_active' => true,
        ]);
        $this->assertNotNull($activation->fresh()->used_at);
        $this->assertTrue(Hash::check('secure-student-password', $student->fresh()->password));

        $this->post('/login', [
            'email' => $student->email,
            'password' => 'secure-student-password',
        ])->assertRedirect('/dashboard');

        $this->post(route('student.activation.activate', ['token' => $token]), [
            'password' => 'another-student-password',
            'password_confirmation' => 'another-student-password',
        ])->assertStatus(410);
    }

    public function test_invalid_rows_are_skipped_while_valid_rows_are_imported(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $batch = $this->uploadCsv($petugas,
            "nis,name,email,class_name\n"
            . "10021,Siswa Valid,valid@example.test,XI RPL 1\n"
            . ",Nama Kosong,tidak.valid@example.test,XI RPL 2\n"
        );

        $this->actingAs($petugas)
            ->post(route('admin.students.import.batch.process', ['batch' => $batch]))
            ->assertRedirect(route('admin.students.import.result'));

        $this->assertDatabaseHas('users', ['nis' => '10021', 'is_active' => false]);
        $this->assertDatabaseMissing('users', ['email' => 'tidak.valid@example.test']);
        $this->get(route('admin.students.import.result'))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('1');
    }

    public function test_activation_link_is_single_use_and_expired_links_are_rejected(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA, ['is_active' => false]);
        $token = str_repeat('A', 64);
        $activation = StudentAccountActivation::create([
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('student.activation.show', ['token' => $token]))->assertOk();
        $this->post(route('student.activation.activate', ['token' => $token]), [
            'password' => 'student-new-password',
            'password_confirmation' => 'student-new-password',
        ])->assertRedirect(route('login'));
        $this->assertNotNull($activation->fresh()->used_at);
        $this->get(route('student.activation.show', ['token' => $token]))->assertStatus(410);

        $expiredStudent = $this->makeUser(User::ROLE_SISWA, ['is_active' => false]);
        $expiredToken = str_repeat('B', 64);
        StudentAccountActivation::create([
            'user_id' => $expiredStudent->id,
            'token_hash' => hash('sha256', $expiredToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('student.activation.show', ['token' => $expiredToken]))->assertStatus(410);
        $this->post(route('student.activation.activate', ['token' => $expiredToken]), [
            'password' => 'student-new-password',
            'password_confirmation' => 'student-new-password',
        ])->assertStatus(410);
        $this->assertFalse($expiredStudent->fresh()->is_active);
    }

    public function test_admin_can_reissue_expired_pending_activation_link_and_invalidates_old_link(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $student = $this->makeUser(User::ROLE_SISWA, ['is_active' => false]);
        $oldToken = str_repeat('C', 64);
        $oldActivation = StudentAccountActivation::create([
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $oldToken),
            'expires_at' => now()->subDay(),
        ]);

        $download = $this->actingAs($petugas)
            ->post(route('admin.students.activation.reissue', $student));
        $download->assertOk();
        $rows = $this->parseCsv($download->streamedContent());
        $newToken = basename((string) parse_url($rows[1][3], PHP_URL_PATH));

        $this->assertNotSame($oldToken, $newToken);
        $this->assertNotNull($oldActivation->fresh()->used_at);
        $this->assertDatabaseHas('student_account_activations', [
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $newToken),
        ]);
        $this->get(route('student.activation.show', ['token' => $oldToken]))->assertStatus(410);
        $this->get(route('student.activation.show', ['token' => $newToken]))->assertOk();
    }

    public function test_pending_activation_students_are_separated_from_manually_deactivated_accounts(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $pending = $this->makeUser(User::ROLE_SISWA, ['nis' => '10031', 'is_active' => false]);
        StudentAccountActivation::create([
            'user_id' => $pending->id,
            'token_hash' => hash('sha256', str_repeat('D', 64)),
            'expires_at' => now()->addDay(),
        ]);
        $inactive = $this->makeUser(User::ROLE_SISWA, ['nis' => '10032', 'is_active' => false]);

        $this->actingAs($petugas)
            ->get(route('admin.students.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Menunggu aktivasi')
            ->assertSee('10031')
            ->assertDontSee('10032');

        $this->get(route('admin.students.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('10032')
            ->assertDontSee('10031');

        $this->from(route('admin.students.index'))
            ->patch(route('admin.students.toggle-status', $pending))
            ->assertSessionHasErrors('account');
    }

    public function test_import_preview_expires_after_one_hour(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $batch = $this->uploadCsv($petugas, "nis,name,email,class_name\n10041,Siswa Expired,expired@example.test,XI RPL 1\n");
        $path = 'student-imports/' . $batch . '.json';
        $payload = json_decode(Storage::disk('local')->get($path), true);
        $payload['created_at'] = now()->subHours(2)->timestamp;
        Storage::disk('local')->put($path, json_encode($payload));

        $this->actingAs($petugas)
            ->get(route('admin.students.import.batch', ['batch' => $batch]))
            ->assertStatus(410);

        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    private function uploadCsv(User $admin, string $csv): string
    {
        $response = $this->actingAs($admin)->post(route('admin.students.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        $this->assertMatchesRegularExpression('~/admin/siswa/impor/([a-f0-9-]+)/pratinjau$~', $location);
        preg_match('~/admin/siswa/impor/([a-f0-9-]+)/pratinjau$~', $location, $matches);

        return $matches[1];
    }

    /** @return array<int, array<int, string>> */
    private function parseCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\n|\r/', trim((string) $contents));

        return array_map(fn ($line) => str_getcsv($line), $lines ?: []);
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
