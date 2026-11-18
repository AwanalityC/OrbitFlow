<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_and_inactive_accounts_receive_the_same_generic_login_error(): void
    {
        $inactive = $this->makeUser('inactive.student@example.test', [
            'is_active' => false,
        ]);

        $unknownResponse = $this->from(route('login'))->post(route('login'), [
            'email' => 'unknown.student@example.test',
            'password' => 'wrong-password-123',
        ]);

        $inactiveResponse = $this->from(route('login'))->post(route('login'), [
            'email' => $inactive->email,
            'password' => 'password123',
        ]);

        $unknownMessage = $unknownResponse->getSession()->get('errors')->first('email');
        $inactiveMessage = $inactiveResponse->getSession()->get('errors')->first('email');

        $this->assertSame($unknownMessage, $inactiveMessage);
        $this->assertSame('Email atau kata sandi salah, atau akun tidak dapat digunakan.', $unknownMessage);
        $this->assertGuest();

        $this->assertSame(2, ActivityLog::where('event_key', 'auth.login.failed')->count());
        $failedLog = ActivityLog::where('event_key', 'auth.login.failed')->firstOrFail();
        $this->assertNull($failedLog->actor_user_id);
        $this->assertNull($failedLog->actor_email);
        $this->assertArrayHasKey('email_fingerprint', $failedLog->after_data);
        $this->assertStringNotContainsString('unknown.student@example.test', json_encode($failedLog->getAttributes()));
        $this->assertStringNotContainsString('wrong-password-123', json_encode($failedLog->getAttributes()));
    }

    public function test_five_failed_attempts_for_the_same_email_and_ip_are_temporarily_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login'), [
                    'email' => 'repeated@example.test',
                    'password' => 'incorrect-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'repeated@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee('Terlalu banyak percobaan login.');

        // A further blocked request should not create another audit alert
        // during the same 60-second throttle window.
        $this->post(route('login'), [
            'email' => 'repeated@example.test',
            'password' => 'incorrect-password',
        ])->assertStatus(429)->assertSee('Terlalu banyak percobaan login.');

        $this->assertSame(5, ActivityLog::where('event_key', 'auth.login.failed')->count());
        $this->assertSame(1, ActivityLog::where('event_key', 'auth.login.throttled')->count());
    }

    public function test_ip_wide_login_limit_is_applied_independently_of_the_email_bucket(): void
    {
        $ip = '127.0.0.1';
        $ipKey = 'orbitflow:login:ip:' . hash_hmac('sha256', $ip, (string) config('app.key'));

        for ($attempt = 0; $attempt < 300; $attempt++) {
            RateLimiter::hit($ipKey, 60);
        }

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'different-account@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee('Terlalu banyak percobaan login.');

        $this->assertDatabaseHas('activity_logs', [
            'event_key' => 'auth.login.throttled',
        ]);
    }

    public function test_successful_login_regenerates_session_and_records_security_activity(): void
    {
        $student = $this->makeUser('login.success@example.test');
        $this->get(route('login'))->assertOk();
        $previousSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => strtoupper($student->email),
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($student);
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $student->id,
            'event_key' => 'auth.login.succeeded',
        ]);
    }


    public function test_successful_login_clears_the_email_and_ip_failure_bucket(): void
    {
        $student = $this->makeUser('clear.bucket@example.test');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('login'), [
                'email' => $student->email,
                'password' => 'incorrect-password',
            ])->assertRedirect(route('login'));
        }

        $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->post('/logout')->assertRedirect('/login');

        // After a successful authentication, the same email+IP pair receives
        // a fresh five-attempt window instead of inheriting old failures.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => $student->email,
                'password' => 'incorrect-password',
            ])->assertRedirect(route('login'));
        }
    }

    public function test_logout_records_security_activity_and_ends_the_session(): void
    {
        $student = $this->makeUser('logout.user@example.test');

        $this->actingAs($student)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $student->id,
            'event_key' => 'auth.logout',
        ]);
    }

    public function test_session_cookies_are_http_only_and_same_site_lax_by_default(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_student_account_creation_requires_a_ten_character_password(): void
    {
        $petugas = $this->makeUser('petugas.create-student@example.test', ['role' => User::ROLE_PETUGAS]);

        $this->actingAs($petugas)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => 'Siswa Lemah',
                'email' => 'weak.student@example.test',
                'nis' => '202699991',
                'class_name' => 'XII RPL 1',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])
            ->assertRedirect(route('admin.students.create'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak.student@example.test']);
    }

    public function test_staff_account_creation_requires_a_ten_character_password(): void
    {
        $superAdmin = $this->makeUser('superadmin.create-staff@example.test', ['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->from(route('admin.staff.create'))
            ->post(route('admin.staff.store'), [
                'name' => 'Petugas Lemah',
                'email' => 'weak.staff@example.test',
                'role' => User::ROLE_PETUGAS,
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])
            ->assertRedirect(route('admin.staff.create'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak.staff@example.test']);
    }

    /** @param array<string, mixed> $attributes */
    private function makeUser(string $email, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Uji',
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_SISWA,
            'is_active' => true,
        ], $attributes));
    }
}
