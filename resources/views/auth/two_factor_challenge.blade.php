@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="login-wrap">
    <div class="login-card" style="max-width: 440px;">
        <div class="login-brand-header">
            <div class="login-crest" style="background: linear-gradient(135deg, #1e3a8a, #0f172a); color: #fbbf24;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 style="font-size: 20px;">{{ __('Two-Factor Authentication') }}</h1>
            <div class="sub">{{ __('Security Verification for Administrative Access') }}</div>
        </div>

        @if($errors->any())
            <div class="alert alert-error" style="margin-bottom: 16px;">
                @foreach($errors->all() as $error)
                    <div><i class="fa-solid fa-circle-exclamation" style="margin-right: 4px;"></i> {{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if($isFirstTime)
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; font-size: 13px;">
                <div style="font-weight: 700; color: #1e293b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-qrcode" style="color: #2563eb;"></i> {{ __('First-Time 2FA Setup') }}
                </div>
                <p style="color: #64748b; margin: 0 0 10px; line-height: 1.4;">
                    {{ __('Open Google Authenticator or your TOTP app and add this account using the manual setup key below:') }}
                </p>
                <div style="background: #fff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 12px; font-family: monospace; font-size: 14px; font-weight: 700; color: #0f172a; text-align: center; letter-spacing: 2px; user-select: all;">
                    {{ chunk_split($secret, 4, ' ') }}
                </div>

                @if(!empty($recoveryCodes))
                    <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                        <span style="font-weight: 600; color: #475569; font-size: 12px;">{{ __('Emergency Recovery Codes (Save these securely):') }}</span>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-top: 6px; font-family: monospace; font-size: 11px; color: #64748b;">
                            @foreach($recoveryCodes as $rc)
                                <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; text-align: center;">{{ $rc }}</code>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('2fa.verify') }}" id="totpForm">
            @csrf

            <!-- Standard 6-digit TOTP input -->
            <div id="totpInputSection">
                <div class="form-group">
                    <label for="code" style="text-align: center; display: block; margin-bottom: 8px;">
                        <i class="fa-solid fa-key" style="color: var(--brand-muted); margin-right: 4px;"></i>
                        {{ __('Enter 6-Digit Authenticator Code') }}
                    </label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="••••••"
                        style="text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: 700; padding: 10px;"
                        autofocus
                    >
                </div>
            </div>

            <!-- Recovery code input (initially hidden) -->
            <div id="recoveryInputSection" style="display: none;">
                <div class="form-group">
                    <label for="recovery_code" style="text-align: center; display: block; margin-bottom: 8px;">
                        <i class="fa-solid fa-life-ring" style="color: var(--brand-muted); margin-right: 4px;"></i>
                        {{ __('Enter Emergency Recovery Code') }}
                    </label>
                    <input
                        type="text"
                        id="recovery_code"
                        name="recovery_code"
                        placeholder="XXXX-XXXX"
                        style="text-align: center; font-size: 18px; letter-spacing: 2px; font-family: monospace; font-weight: 700; padding: 10px;"
                    >
                </div>
            </div>

            <button type="submit" class="btn" style="width: 100%; padding: 10px; font-size: 15px; margin-top: 8px;">
                <i class="fa-solid fa-unlock-keyhole" style="margin-right: 6px;"></i> {{ __('Verify & Sign In') }}
            </button>
        </form>

        <div style="margin-top: 14px; text-align: center;">
            <button
                type="button"
                id="toggleRecoveryBtn"
                style="background: none; border: none; color: #2563eb; font-size: 13px; text-decoration: underline; cursor: pointer; padding: 0;"
            >
                {{ __('Lost device? Use emergency recovery code') }}
            </button>
        </div>

        <div style="margin-top: 16px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 12px;">
            <a href="{{ route('2fa.cancel') }}" style="color: #64748b; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Cancel and return to login') }}
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('toggleRecoveryBtn');
        const totpSec = document.getElementById('totpInputSection');
        const recSec = document.getElementById('recoveryInputSection');
        const codeInput = document.getElementById('code');
        const recInput = document.getElementById('recovery_code');

        let isRecovery = false;

        toggleBtn.addEventListener('click', () => {
            isRecovery = !isRecovery;
            if (isRecovery) {
                totpSec.style.display = 'none';
                recSec.style.display = 'block';
                codeInput.value = '';
                recInput.focus();
                toggleBtn.textContent = '{{ __("Use standard 6-digit authenticator code") }}';
            } else {
                totpSec.style.display = 'block';
                recSec.style.display = 'none';
                recInput.value = '';
                codeInput.focus();
                toggleBtn.textContent = '{{ __("Lost device? Use emergency recovery code") }}';
            }
        });
    });
</script>
@endsection
