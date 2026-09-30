<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceInactivityTimeout
{
    /**
     * Timeout duration in seconds (15 minutes = 900 seconds).
     */
    public const DEFAULT_TIMEOUT_SECONDS = 900;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeout = (int) config('session.inactivity_timeout', self::DEFAULT_TIMEOUT_SECONDS);
            $lastActivity = $request->session()->get('last_activity_time');
            $currentTime = time();

            if ($lastActivity && ($currentTime - $lastActivity > $timeout)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your session has expired due to 15 minutes of inactivity.',
                        'code' => 'SESSION_TIMEOUT',
                    ], 401);
                }

                return redirect()->route('login', ['reason' => 'inactivity'])
                    ->with('warning', 'Your session has expired due to 15 minutes of inactivity. Please sign in again.');
            }

            $request->session()->put('last_activity_time', $currentTime);
        }

        return $next($request);
    }
}
