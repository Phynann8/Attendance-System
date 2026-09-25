@extends('layouts.app')

@section('title', __('Role & Access Control Management'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Role-Based Access Control (RBAC)') }}</div>
        <div class="page-sub">{{ __('Define custom roles and configure which modules and features each role can open.') }}</div>
    </div>
    <a href="{{ route('super-admin.roles.create') }}" class="btn">+ {{ __('Create Custom Role') }}</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('Role') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Assigned Users') }}</th>
                <th>{{ __('Permitted Modules & Features') }}</th>
                <th style="text-align: right;">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($roles as $role)
                <tr>
                    <td style="max-width: 220px;">
                        <strong>{{ $role->name }}</strong>
                        <div style="font-size: 13px; color: var(--muted);">{{ $role->slug }}</div>
                        @if($role->description)
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">{{ __($role->description) }}</div>
                        @endif
                    </td>
                    <td>
                        @if($role->is_system)
                            <span class="badge badge-primary">{{ __('System Role') }}</span>
                        @else
                            <span class="badge badge-violet">{{ __('Custom Role') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('super-admin.users.index', ['role_id' => $role->id]) }}" style="font-weight: 600;">
                            {{ $role->users_count }} {{ __('user(s)') }}
                        </a>
                    </td>
                    <td>
                        @if($role->slug === 'super_admin')
                            <span class="badge badge-green">★ {{ __('Full System Access (All Modules)') }}</span>
                        @elseif($role->systemPermissions->isEmpty())
                            <span style="font-size: 13px; color: var(--muted); font-style: italic;">{{ __('No module permissions granted') }}</span>
                        @else
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 420px;">
                                @foreach($role->systemPermissions as $perm)
                                    <span class="badge badge-slate" style="font-size: 11px;" title="{{ __($perm->description) }}">
                                        {{ __($perm->name) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                            <a href="{{ route('super-admin.roles.edit', $role->id) }}" class="btn btn-sm btn-secondary">
                                {{ __('Configure') }}
                            </a>

                            @if(! $role->is_system)
                                <form method="POST" action="{{ route('super-admin.roles.destroy', $role->id) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete custom role') }} \'{{ addslashes($role->name) }}\'?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm" style="background: #ef4444; color: white;" @disabled($role->users_count > 0) title="{{ $role->users_count > 0 ? __('Cannot delete role assigned to users') : __('Delete this custom role') }}">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 24px; color: var(--muted);">
                        {{ __('No roles found.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
