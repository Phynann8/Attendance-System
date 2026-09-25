@extends('layouts.app')

@section('title', __('Create Custom Role'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Create Custom Role') }}</div>
        <div class="page-sub">{{ __('Define a new role and choose exactly which modules and features it can open.') }}</div>
    </div>
    <a href="{{ route('super-admin.roles.index') }}" class="btn btn-secondary">← {{ __('Back to Roles') }}</a>
</div>

<form method="POST" action="{{ route('super-admin.roles.store') }}">
    @csrf

    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 16px;">{{ __('Role Information') }}</h3>

        <div class="grid grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
                <label for="name">{{ __('Role Name') }} <span style="color: var(--red);">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g. Attendance Auditor') }}">
                @error('name')
                    <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="slug">{{ __('Identifier / Slug') }} <span style="font-weight: normal; color: var(--muted);">({{ __('optional, will auto-generate') }})</span></label>
                <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="e.g. attendance_auditor">
                @error('slug')
                    <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label for="description">{{ __('Role Description') }}</label>
            <textarea id="description" name="description" rows="2" placeholder="{{ __('Briefly describe what responsibilities this role has…') }}">{{ old('description') }}</textarea>
            @error('description')
                <div style="color: var(--red); font-size: 13px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="flex-between" style="margin-bottom: 16px;">
            <div>
                <h3>{{ __('Module & Feature Permissions') }}</h3>
                <div style="font-size: 13px; color: var(--muted);">{{ __('Check the modules and operations this role is allowed to open.') }}</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllPermissions(true)">{{ __('Select All') }}</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllPermissions(false)">{{ __('Deselect All') }}</button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
            @foreach($permissionsByModule as $moduleName => $permissions)
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 16px; background: #fafafa;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 12px; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">
                        {{ __($moduleName) }} {{ __('Module') }}
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        @foreach($permissions as $perm)
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 14px;">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="perm-checkbox" {{ in_array($perm->id, old('permissions', [])) ? 'checked' : '' }} style="margin-top: 3px; width: auto;">
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
        <button type="submit" class="btn">{{ __('Create Custom Role') }}</button>
        <a href="{{ route('super-admin.roles.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
    function toggleAllPermissions(checked) {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = checked);
    }
</script>
@endsection
