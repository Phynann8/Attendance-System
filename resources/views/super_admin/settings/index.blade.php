@extends('layouts.app')

@section('title', __('School Profile & Settings'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('School Profile & System Settings') }}</div>
        <div class="page-sub">{{ __('Configure school branding, contact details, academic year, and morning attendance schedule.') }}</div>
    </div>
</div>

<form method="POST" action="{{ route('super-admin.settings.update') }}">
    @csrf
    @method('PUT')

    @foreach($settings as $group => $items)
        <div class="card" style="margin-bottom: 20px;">
            <div style="font-weight: 700; font-size: 16px; margin-bottom: 16px; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">
                {{ __(ucfirst($group)) }} {{ __('Configuration') }}
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                @foreach($items as $setting)
                    <div class="form-group">
                        <label for="setting_{{ $setting->key }}" style="font-weight: 600;">
                            {{ __(ucwords(str_replace('_', ' ', $setting->key))) }}
                        </label>
                        <input type="text"
                               id="setting_{{ $setting->key }}"
                               name="settings[{{ $setting->key }}]"
                               value="{{ old('settings.' . $setting->key, $setting->value) }}"
                               placeholder="{{ __('Enter value…') }}">
                        @if($setting->description)
                            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">{{ __($setting->description) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div style="display: flex; gap: 12px;">
        <button type="submit" class="btn">{{ __('Save & Update Settings') }}</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
