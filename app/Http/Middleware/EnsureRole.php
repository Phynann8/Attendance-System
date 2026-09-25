<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow only users whose role is in the provided list, super admins,
     * or custom roles with matching module permissions.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account is inactive. Please contact an administrator.');
        }

        // Super Admin has access across the system
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Direct role match
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Custom role module permission check
        foreach ($roles as $role) {
            if ($user->hasPermission("module.{$role}") || $user->hasPermission($role)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
