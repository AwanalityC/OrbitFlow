<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = $credentials['email'];
        $ipAddress = (string) ($request->ip() ?? 'unknown');
        $identityKey = $this->identityRateLimitKey($email, $ipAddress);
        $ipKey = $this->ipRateLimitKey($ipAddress);

        $identityBlocked = RateLimiter::tooManyAttempts($identityKey, self::IDENTITY_MAX_ATTEMPTS);
        $ipBlocked = RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS);

        if ($identityBlocked || $ipBlocked) {
            $retryAfter = max(
                1,
                $identityBlocked ? RateLimiter::availableIn($identityKey) : 0,
                $ipBlocked ? RateLimiter::availableIn($ipKey) : 0,
            );

            $this->recordThrottleEventOnce($email, $ipAddress, $retryAfter, $ipBlocked);

            return response()->view('auth.login', [
                'throttleMessage' => 'Terlalu banyak percobaan login. Silakan coba kembali dalam ' . $retryAfter . ' detik.',
                'prefillEmail' => $email,
            ], 429, [
                'Retry-After' => (string) $retryAfter,
                'Cache-Control' => 'no-store, private',
            ]);
        }

        if (Auth::attempt($credentials)) {
            $authenticatedUser = Auth::user();

            if (! $authenticatedUser || $authenticatedUser->is_active === false) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $this->rejectLogin($email, $identityKey, $ipKey);
            }

            // A successful login clears only the email+IP bucket. The broader
            // IP bucket is intentionally retained to discourage IP-wide abuse.
            RateLimiter::clear($identityKey);
            $request->session()->regenerate();
            $request->session()->put([
                'auth.password_hash_user_id' => (string) $authenticatedUser->getAuthIdentifier(),
                'auth.password_hash_fingerprint' => hash('sha256', $authenticatedUser->getAuthPassword()),
            ]);

            ActivityLogger::record(
                'security',
                'auth.login.succeeded',
                'Pengguna berhasil masuk ke OrbitFlow.',
                $authenticatedUser,
                null,
                ['role' => $authenticatedUser->role],
                $authenticatedUser,
                $authenticatedUser->name . ' · ' . $authenticatedUser->email
            );

            return redirect()->intended('/dashboard');
        }

        return $this->rejectLogin($email, $identityKey, $ipKey);
    }

    public function logout(Request $request)
    {
        $authenticatedUser = Auth::user();

        if ($authenticatedUser) {
            ActivityLogger::record(
                'security',
                'auth.logout',
                'Pengguna keluar dari OrbitFlow.',
                $authenticatedUser,
                null,
                ['role' => $authenticatedUser->role],
                $authenticatedUser,
                $authenticatedUser->name . ' · ' . $authenticatedUser->email
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private const IDENTITY_MAX_ATTEMPTS = 5;
    private const IP_MAX_ATTEMPTS = 300;
    private const DECAY_SECONDS = 60;

    private function rejectLogin(string $email, string $identityKey, string $ipKey)
    {
        RateLimiter::hit($identityKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);

        ActivityLogger::recordAnonymous(
            'security',
            'auth.login.failed',
            'Percobaan login ditolak.',
            [
                'email_fingerprint' => $this->emailFingerprint($email),
                'result' => 'credentials_rejected',
            ],
            'Percobaan login tidak berhasil'
        );

        return redirect()->route('login')
            ->withErrors(['email' => 'Email atau kata sandi salah, atau akun tidak dapat digunakan.'])
            ->onlyInput('email');
    }

    private function recordThrottleEventOnce(string $email, string $ipAddress, int $retryAfter, bool $ipBlocked): void
    {
        $scope = $ipBlocked ? 'ip:' . $ipAddress : 'identity:' . $email . '|' . $ipAddress;
        $marker = 'orbitflow:login:throttle-audit:' . hash_hmac('sha256', $scope, (string) config('app.key'));

        if (! Cache::add($marker, true, self::DECAY_SECONDS)) {
            return;
        }

        ActivityLogger::recordAnonymous(
            'security',
            'auth.login.throttled',
            'Percobaan login dibatasi sementara karena terlalu banyak kegagalan.',
            [
                'email_fingerprint' => $this->emailFingerprint($email),
                'scope' => $ipBlocked ? 'ip' : 'email_ip',
                'retry_after_seconds' => $retryAfter,
            ],
            'Percobaan login dibatasi sementara'
        );
    }

    private function identityRateLimitKey(string $email, string $ipAddress): string
    {
        $fingerprint = hash_hmac('sha256', $email . '|' . $ipAddress, (string) config('app.key'));

        return 'orbitflow:login:identity:' . $fingerprint;
    }

    private function ipRateLimitKey(string $ipAddress): string
    {
        $fingerprint = hash_hmac('sha256', $ipAddress, (string) config('app.key'));

        return 'orbitflow:login:ip:' . $fingerprint;
    }

    private function emailFingerprint(string $email): string
    {
        return hash_hmac('sha256', $email, (string) config('app.key'));
    }

}
