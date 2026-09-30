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
            <input type="password" id="password" name="password" required placeholder="{{ __('Minimum 8 characters') }}">
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

        <div class="form-group" style="margin-bottom: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <label style="font-weight: 600; color: #334155; margin-bottom: 0;">
                    {{ __('Assigned Campuses') }}
                </label>
                <div style="font-size: 12px; display: flex; gap: 8px;">
                    <a href="javascript:void(0)" onclick="selectAllCampuses(true)" style="color: var(--brand-blue, #0284c7); text-decoration: none; font-weight: 600;">{{ __('Select All') }}</a>
                    <span style="color: #cbd5e1;">|</span>
                    <a href="javascript:void(0)" onclick="selectAllCampuses(false)" style="color: #64748b; text-decoration: none;">{{ __('Clear All') }}</a>
                </div>
            </div>
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                @php
                    $selectedCampusIds = old('campus_ids', old('campus_id') ? [(int)old('campus_id')] : []);
                @endphp
                @foreach($campuses as $campus)
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px 10px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; margin: 0; font-size: 13px;">
                        <input type="checkbox" name="campus_ids[]" value="{{ $campus->id }}" class="campus-checkbox"
                            @checked(in_array($campus->id, $selectedCampusIds))
                            style="width: 16px; height: 16px; accent-color: var(--brand-blue, #0284c7); cursor: pointer;">
                        <div>
                            <strong style="color: #0f172a;">{{ $campus->code }}</strong>
                            <span class="muted small" style="font-size: 11px; margin-left: 2px;">({{ $campus->name_en }})</span>
                        </div>
                    </label>
                @endforeach
            </div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 5px;">
                {{ __('Select one or more campuses this user has access to. If none selected and role is Super Admin, access is system-wide.') }}
            </div>
            @error('campus_ids')
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

<script>
function selectAllCampuses(check) {
    document.querySelectorAll('.campus-checkbox').forEach(cb => cb.checked = check);
}
</script>
@endsection
