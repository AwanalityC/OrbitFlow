<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PasswordRecoveryController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        // Keep the response identical for unknown, inactive, and active accounts
        // to avoid revealing which email addresses are registered.
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'Jika email terdaftar dan akun masih aktif, petunjuk pemulihan kata sandi akan dikirim ke email tersebut.');
    }

    public function showResetForm(Request $request, string $token)
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->is_active || ! Password::broker()->tokenExists($user, $token)) {
            return response()
                ->view('auth.password-reset-invalid')
                ->setStatusCode(410)
                ->header('Cache-Control', 'no-store, private')
                ->header('Referrer-Policy', 'no-referrer');
        }

        return response()
            ->view('auth.reset-password', [
                'token' => $token,
                'email' => $email,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'password.min' => 'Kata sandi baru harus terdiri dari minimal 10 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $eligibleUser = User::query()->where('email', $email)->where('is_active', true)->exists();

        if (! $eligibleUser) {
            return back()->withErrors([
                'email' => 'Tautan pemulihan tidak valid, sudah digunakan, atau telah kedaluwarsa.',
            ])->withInput($request->only('email'));
        }

        $resetCompleted = false;
        $status = Password::reset(
            [
                'email' => $email,
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password) use (&$resetCompleted): void {
                DB::transaction(function () use ($user, $password, &$resetCompleted): void {
                    $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

                    // An account may have been deactivated after its link was issued.
                    if (! $lockedUser || ! $lockedUser->is_active) {
                        return;
                    }

                    $lockedUser->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                        'password_changed_at' => now(),
                    ])->save();

                    event(new PasswordReset($lockedUser));
                    $this->revokeDatabaseSessions($lockedUser);
                    ActivityLogger::record(
                        'security',
                        'password.reset.completed',
                        'Pengguna ' . $lockedUser->name . ' berhasil memperbarui kata sandi melalui pemulihan.',
                        $lockedUser,
                        null,
                        ['method' => 'password_broker', 'account_role' => $lockedUser->role],
                        $lockedUser,
                        $lockedUser->name . ' · ' . $lockedUser->email
                    );
                    $resetCompleted = true;
                });
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetCompleted) {
            return back()->withErrors([
                'email' => 'Tautan pemulihan tidak valid, sudah digunakan, atau telah kedaluwarsa.',
            ])->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru.');
    }

    public function issueStudentRecoveryLink(User $student)
    {
        abort_unless($student->isStudent(), 404);

        return $this->issueAdministrativeLink($student, 'pemulihan kata sandi siswa');
    }

    public function issuePetugasRecoveryLink(User $staff)
    {
        abort_unless($staff->role === User::ROLE_PETUGAS, 404);

        return $this->issueAdministrativeLink($staff, 'pemulihan kata sandi petugas');
    }

    private function issueAdministrativeLink(User $user, string $purpose)
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'account' => 'Tautan pemulihan hanya dapat diterbitkan untuk akun yang aktif.',
            ]);
        }

        $token = Password::broker()->createToken($user);
        $resetUrl = URL::route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        ActivityLogger::record(
            'security',
            'password_recovery.admin_link_issued',
            'Tautan pemulihan kata sandi diterbitkan untuk ' . $purpose . '.',
            $user,
            null,
            ['account_email' => $user->email, 'account_role' => $user->role, 'expires_in_minutes' => (int) config('auth.passwords.users.expire', 30)],
            null,
            $user->name . ' · ' . $user->email
        );

        return response()
            ->view('auth.admin-password-reset-link', [
                'targetUser' => $user,
                'resetUrl' => $resetUrl,
                'purpose' => $purpose,
                'expiresMinutes' => (int) config('auth.passwords.users.expire', 30),
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function revokeDatabaseSessions(User $user): void
    {
        // The project defaults to Laravel's database session driver. Deleting
        // these rows immediately signs the account out on every known device.
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $user->id)->delete();
        }
    }
}
