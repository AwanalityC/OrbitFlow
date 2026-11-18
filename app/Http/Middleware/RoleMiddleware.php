<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        $allowedRoles = match ($role) {
            // The existing admin workspace is available to both administrative
            // roles. Legacy `admin` is treated as Super Admin for compatibility.
            'admin' => ['admin', 'super_admin', 'petugas'],
            'super_admin' => ['admin', 'super_admin'],
            default => [$role],
        };

        if (! $user || ! in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
