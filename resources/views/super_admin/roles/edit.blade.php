@extends('layouts.app')

@section('title', __('Configure Role') . ' — ' . $role->name)

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Configure Role') }}: {{ $role->name }}</div>
        <div class="page-sub">
            @if($role->is_system)
                <span class="badge badge-primary">{{ __('System Role') }}</span> {{ __('Core platform role.') }}
            @else
                <span class="badge badge-violet">{{ __('Custom Role') }}</span>
            @endif
            {{ __('Manage permitted modules and features for this role.') }}
        </div>
    </div>
    <a href="{{ route('super-admin.roles.index') }}" class="btn btn-secondary">← {{ __('Back to Roles') }}</a>
</div>

<form method="POST" action="{{ route('super-admin.roles.update', $role->id) }}">
    @csrf
    @method('PUT')

    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 16px;">{{ __('Role Details') }}</h3>

        <div class="grid grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
                <label for="name">{{ __('Role Name') }} <span style="color: var(--red);">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" required>
                @error('name')
                    <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="slug">{{ __('Identifier / Slug') }}</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug', $role->slug) }}" @readonly($role->is_system) style="{{ $role->is_system ? 'background: #f1f5f9; cursor: not-allowed;' : '' }}">
                @if($role->is_system)
                    <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">{{ __('System role slugs are locked to prevent framework regressions.') }}</div>
                @endif
                @error('slug')
                    <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label for="description">{{ __('Role Description') }}</label>
            <textarea id="description" name="description" rows="2">{{ old('description', $role->description) }}</textarea>
            @error('description')
                <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @if($role->slug === 'super_admin')
        <div class="card" style="margin-bottom: 20px; background: #f0fdf4; border-color: #bbf7d0;">
            <div style="display: flex; gap: 12px; align-items: center;">
                <span style="font-size: 24px;">🛡️</span>
                <div>
                    <strong>{{ __('Super Administrator Role Privilege:') }}</strong>
                    <div style="font-size: 13px; color: #166534;">
                        {{ __('The Super Administrator role automatically possesses unrestricted access across all current and future modules.') }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 20px;">
        <div class="flex-between" style="margin-bottom: 16px;">
            <div>
                <h3>{{ __('Module & Feature Permissions') }}</h3>
                <div style="font-size: 13px; color: var(--muted);">{{ __('Choose which modules users with this role can open.') }}</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllPermissions(true)">{{ __('Select All') }}</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllPermissions(false)">{{ __('Deselect All') }}</button>
            </div>
        </div>

        @php
            $currentPermIds = old('permissions', $role->systemPermissions->pluck('id')->toArray());
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
            @foreach($permissionsByModule as $moduleName => $permissions)
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 16px; background: #fafafa;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 12px; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">
                        {{ __($moduleName) }} {{ __('Module') }}
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        @foreach($permissions as $perm)
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 14px;">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="perm-checkbox" {{ in_array($perm->id, $currentPermIds) ? 'checked' : '' }} style="margin-top: 3px; width: auto;">
                                <div>
                                    <div style="font-weight: 600; color: #1e293b;">{{ __($perm->name) }}</div>
                                    @if($perm->description)
                                        <div style="font-size: 12px; color: var(--muted); line-height: 1.3;">{{ __($perm->description) }}</div>
                                    @endif
                                    <div style="font-size: 11px; color: #94a3b8;">{{ $perm->slug }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @error('permissions')
            <div style="color: var(--red); font-size: 13px; margin-top: 10px;">{{ $message }}</div>
        @enderror
    </div>

    <div style="display: flex; gap: 12px;">
        <button type="submit" class="btn">{{ __('Save Role Changes') }}</button>
        <a href="{{ route('super-admin.roles.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
    function toggleAllPermissions(checked) {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = checked);
    }
</script>
@endsection
