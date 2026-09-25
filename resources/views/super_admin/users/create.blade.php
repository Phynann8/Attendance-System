@extends('layouts.app')

@section('title', __('Add New User'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Add New User') }}</div>
        <div class="page-sub">{{ __('Create an account and assign a role to govern access.') }}</div>
    </div>
    <a href="{{ route('super-admin.users.index') }}" class="btn btn-secondary">← {{ __('Back to Users') }}</a>
</div>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="{{ route('super-admin.users.store') }}">
        @csrf

        <div class="form-group" style="margin-bottom: 16px;">
            <label for="name">{{ __('Full Name') }} <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g. John Doe') }}">
            @error('name')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label for="email">{{ __('Email Address') }} <span class="required">*</span></label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="{{ __('e.g. john@school.test') }}">
            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label for="password">{{ __('Password') }} <span class="required">*</span></label>
            <input type="password" id="password" name="password" required placeholder="{{ __('Minimum 6 characters') }}">
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label for="role_id">{{ __('Assign Role') }} <span class="required">*</span></label>
            <select id="role_id" name="role_id" required>
                <option value="">-- {{ __('Select a role') }} --</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>
                        {{ $role->name }} ({{ $role->slug }})
                        @if($role->is_system) [{{ __('System Role') }}] @else [{{ __('Custom Role') }}] @endif
                    </option>
                @endforeach
            </select>
            @error('role_id')
                <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label for="campus_id">{{ __('Assigned Campus') }}</label>
            <select id="campus_id" name="campus_id">
                <option value="">-- {{ __('All Campuses / Central') }} --</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected(old('campus_id') == $campus->id)>
                        {{ $campus->code }} — {{ $campus->name_en }} ({{ $campus->name_kh }})
                    </option>
                @endforeach
            </select>
            <div style="font-size: 13px; color: var(--muted); margin-top: 2px;">
                {{ __("Leave empty for system-wide access (Super Admin), or select a campus to isolate the user's data.") }}
            </div>
            @error('campus_id')
                <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} style="width: auto;">
                <span>{{ __('Active Account (User can log in)') }}</span>
            </label>
            <div style="font-size: 13px; color: var(--muted); margin-top: 2px;">
                {{ __('Unchecking this will mark the user inactive and prevent them from signing in.') }}
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn">{{ __('Create User') }}</button>
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
