<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        if ($request->query('reason') === 'inactivity' && ! session()->has('warning')) {
            session()->flash('warning', 'Your session has expired due to 15 minutes of inactivity. Please sign in again.');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact a super administrator.',
            ]);
        }

        // Two-Factor Authentication (2FA / TOTP) for Administrators (ATTEND-18)
        if ($user->isAdmin() || $user->hasTwoFactorEnabled()) {
            Auth::logout();
            $request->session()->put('2fa:user_id', $user->id);
            $request->session()->put('2fa:remember', $request->boolean('remember'));

            return redirect()->route('2fa.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->query('reason') === 'inactivity' || $request->input('reason') === 'inactivity') {
            return redirect()->route('login', ['reason' => 'inactivity'])
                ->with('warning', 'Your session has expired due to 15 minutes of inactivity. Please sign in again.');
        }

        return redirect()->route('login')->with('info', 'You have been signed out.');
    }
}
