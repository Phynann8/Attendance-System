<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthController extends Controller
{
    /**
     * Show the 2FA challenge form.
     */
    public function showChallenge(Request $request)
    {
        $userId = $request->session()->get('2fa:user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user) {
            $request->session()->forget(['2fa:user_id', '2fa:remember']);

            return redirect()->route('login');
        }

        // If secret is not yet generated, generate it now
        if (empty($user->two_factor_secret)) {
            $user->two_factor_secret = TwoFactorAuthService::generateSecretKey();
            $user->two_factor_recovery_codes = TwoFactorAuthService::generateRecoveryCodes();
            $user->save();
        }

        $isFirstTime = is_null($user->two_factor_confirmed_at);
        $otpAuthUrl = TwoFactorAuthService::getOtpAuthUrl(
            config('app.name', 'School Attendance'),
            $user->email,
            $user->two_factor_secret
        );

        return view('auth.two_factor_challenge', [
            'user' => $user,
            'isFirstTime' => $isFirstTime,
            'secret' => $user->two_factor_secret,
            'otpAuthUrl' => $otpAuthUrl,
            'recoveryCodes' => $isFirstTime ? $user->two_factor_recovery_codes : [],
        ]);
    }

    /**
     * Verify the submitted 2FA code or recovery code.
     */
    public function verifyChallenge(Request $request)
    {
        $userId = $request->session()->get('2fa:user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $code = trim((string) $request->input('code'));
        $recoveryCode = trim((string) $request->input('recovery_code'));

        $verified = false;

        // 1. Try TOTP 6-digit code
        if (! empty($code)) {
            $verified = TwoFactorAuthService::verifyCode($user->two_factor_secret, $code);
        }

        // 2. Try Emergency Recovery Code
        if (! $verified && ! empty($recoveryCode)) {
            $normalizedInput = strtoupper(str_replace([' ', '-'], '', $recoveryCode));
            $recoveryCodes = (array) ($user->two_factor_recovery_codes ?? []);

            foreach ($recoveryCodes as $index => $existingCode) {
                $normalizedExisting = strtoupper(str_replace([' ', '-'], '', $existingCode));
                if (hash_equals($normalizedExisting, $normalizedInput)) {
                    // Consume the used recovery code
                    unset($recoveryCodes[$index]);
                    $user->two_factor_recovery_codes = array_values($recoveryCodes);
                    $user->save();
                    $verified = true;
                    break;
                }
            }
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor authentication code is invalid or expired.',
            ]);
        }

        // If first verification, record confirmation timestamp
        if (is_null($user->two_factor_confirmed_at)) {
            $user->two_factor_confirmed_at = now();
            $user->save();
        }

        $remember = (bool) $request->session()->pull('2fa:remember', false);
        $request->session()->forget('2fa:user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        AuditService::log('auth.2fa.verified', details: [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Cancel the 2FA flow and return to login.
     */
    public function cancel(Request $request)
    {
        $request->session()->forget(['2fa:user_id', '2fa:remember']);

        return redirect()->route('login');
    }
}
