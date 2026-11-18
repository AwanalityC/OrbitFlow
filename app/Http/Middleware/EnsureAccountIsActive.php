<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            return $this->logoutForSecurity($request, 'Akun ini sedang dinonaktifkan. Hubungi administrator sekolah.');
        }

        if ($user) {
            $currentPasswordFingerprint = hash('sha256', $user->getAuthPassword());
            $currentUserId = (string) $user->getAuthIdentifier();
            $sessionPasswordFingerprint = $request->session()->get('auth.password_hash_fingerprint');
            $sessionFingerprintUserId = $request->session()->get('auth.password_hash_user_id');

            // Bind the fingerprint to its account. This prevents a session that
            // authenticates as a different user (for example, after an account
            // switch in a long-lived session or in a test) from comparing the
            // new user's password hash with the previous user's fingerprint.
            // Legacy sessions without the user ID get a fresh baseline once.
            if (
                $sessionFingerprintUserId === null
                || (string) $sessionFingerprintUserId !== $currentUserId
                || $sessionPasswordFingerprint === null
            ) {
                $request->session()->put([
                    'auth.password_hash_user_id' => $currentUserId,
                    'auth.password_hash_fingerprint' => $currentPasswordFingerprint,
                ]);
            } elseif (! hash_equals((string) $sessionPasswordFingerprint, $currentPasswordFingerprint)) {
                return $this->logoutForSecurity($request, 'Kata sandi akun telah diubah. Silakan masuk kembali.');
            }
        }

        return $next($request);
    }

    private function logoutForSecurity(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
