<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    public function test_login_page_links_to_forgot_password_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Lupa kata sandi?')
            ->assertSee('Alamat email');
    }

    public function test_password_reset_request_uses_generic_message_and_notifies_active_user(): void
    {
        Notification::fake();
        $student = $this->makeUser(User::ROLE_SISWA, ['email' => 'active.student@example.test']);

        $response = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $student->email]);

        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status');
        Notification::assertSentTo($student, ResetPassword::class);
    }

    public function test_password_reset_request_does_not_disclose_unknown_or_inactive_accounts(): void
    {
        Notification::fake();
        $inactiveStudent = $this->makeUser(User::ROLE_SISWA, [
            'email' => 'inactive.student@example.test',
            'is_active' => false,
        ]);

        $inactiveResponse = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $inactiveStudent->email]);
        $unknownResponse = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.test']);

        $inactiveResponse->assertSessionHas('status');
        $unknownResponse->assertSessionHas('status');
        $this->assertSame(
            $inactiveResponse->getSession()->get('status'),
            $unknownResponse->getSession()->get('status')
        );
        Notification::assertNothingSent();
    }

    public function test_active_user_can_reset_password_once_and_timestamp_is_updated(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA);
        $oldPasswordHash = $student->password;
        $token = Password::broker()->createToken($student);

        $this->get(route('password.reset', ['token' => $token, 'email' => $student->email]))
            ->assertOk()
            ->assertSee('Buat kata sandi baru');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'))
          ->assertSessionHas('status');

        $student->refresh();
        $this->assertNotSame($oldPasswordHash, $student->password);
        $this->assertTrue(Hash::check('new-secure-password', $student->password));
        $this->assertNotNull($student->password_changed_at);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'another-secure-password',
            'password_confirmation' => 'another-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('new-secure-password', $student->fresh()->password));
    }


    public function test_expired_reset_link_is_rejected_before_showing_the_password_form(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA);
        $token = Password::broker()->createToken($student);

        $this->travel(31)->minutes();

        $this->get(route('password.reset', ['token' => $token, 'email' => $student->email]))
            ->assertStatus(410)
            ->assertSee('Tautan pemulihan tidak berlaku');
    }

    public function test_inactive_account_cannot_use_a_previously_issued_reset_token(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA, ['is_active' => true]);
        $token = Password::broker()->createToken($student);
        $student->update(['is_active' => false]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-secure-password', $student->fresh()->password));
    }

    public function test_petugas_can_issue_a_one_time_recovery_link_for_an_active_student(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $student = $this->makeUser(User::ROLE_SISWA);

        $response = $this->actingAs($petugas)
            ->post(route('admin.students.password-reset-link', $student));

        $response->assertOk()
            ->assertSee('Tautan reset kata sandi')
            ->assertSee($student->email)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        preg_match('/<input[^>]*id="reset-link"[^>]*value="([^"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $resetUrl = html_entity_decode($matches[1]);
        $this->assertStringContainsString('email=' . urlencode($student->email), $resetUrl);

        $parts = parse_url($resetUrl);
        parse_str($parts['query'] ?? '', $query);
        $resetPath = ($parts['path'] ?? '/') . '?' . http_build_query($query);
        $this->get($resetPath)
            ->assertOk()
            ->assertSee('Buat kata sandi baru');

        $token = basename($parts['path'] ?? '');
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'student-recovered-password',
            'password_confirmation' => 'student-recovered-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('student-recovered-password', $student->fresh()->password));
        $this->get($resetPath)->assertStatus(410);
    }

    public function test_petugas_cannot_issue_recovery_links_for_staff_accounts(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $targetPetugas = $this->makeUser(User::ROLE_PETUGAS);

        $this->actingAs($petugas)
            ->post(route('admin.staff.password-reset-link', $targetPetugas))
            ->assertForbidden();
    }

    public function test_super_admin_can_issue_recovery_link_for_active_petugas(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $petugas = $this->makeUser(User::ROLE_PETUGAS);

        $response = $this->actingAs($superAdmin)
            ->post(route('admin.staff.password-reset-link', $petugas));

        $response->assertOk()
            ->assertSee('Tautan reset kata sandi')
            ->assertSee($petugas->email);
    }

    public function test_admin_assisted_recovery_rejects_inactive_accounts(): void
    {
        $petugas = $this->makeUser(User::ROLE_PETUGAS);
        $inactiveStudent = $this->makeUser(User::ROLE_SISWA, ['is_active' => false]);

        $this->actingAs($petugas)
            ->from(route('admin.students.index'))
            ->post(route('admin.students.password-reset-link', $inactiveStudent))
            ->assertSessionHasErrors('account');
    }

    public function test_password_change_invalidates_a_session_with_an_old_password_fingerprint(): void
    {
        $student = $this->makeUser(User::ROLE_SISWA);
        $oldPassword = $student->getAuthPassword();
        $oldFingerprint = hash('sha256', $oldPassword);
        $sessionFingerprint = [
            'auth.password_hash_user_id' => (string) $student->getAuthIdentifier(),
            'auth.password_hash_fingerprint' => $oldFingerprint,
        ];

        $this->actingAs($student)
            ->withSession($sessionFingerprint)
            ->get(route('siswa.dashboard'))
            ->assertOk();

        $student->update([
            'password' => 'replacement-password-for-session-test',
            'password_changed_at' => now(),
        ]);
        $student->refresh();

        $this->actingAs($student)
            ->withSession($sessionFingerprint)
            ->get(route('siswa.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_password_fingerprint_is_reinitialized_when_authenticated_user_changes(): void
    {
        $firstStudent = $this->makeUser(User::ROLE_SISWA, ['email' => 'first.student@example.test']);
        $secondStudent = $this->makeUser(User::ROLE_SISWA, ['email' => 'second.student@example.test']);

        $this->actingAs($firstStudent)
            ->get(route('siswa.dashboard'))
            ->assertOk();

        $this->actingAs($secondStudent)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSessionHas('auth.password_hash_user_id', (string) $secondStudent->getAuthIdentifier())
            ->assertSessionHas(
                'auth.password_hash_fingerprint',
                hash('sha256', $secondStudent->getAuthPassword())
            );
    }
}
