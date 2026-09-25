@extends('layouts.app')

@section('title', __('Super Admin Dashboard'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Super Administration') }}</div>
        <div class="page-sub">{{ __('System-wide governance, user management, and Role-Based Access Control (RBAC).') }}</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="{{ route('super-admin.users.create') }}" class="btn">+ {{ __('Add User') }}</a>
        <a href="{{ route('super-admin.roles.create') }}" class="btn btn-secondary">+ {{ __('Create Role') }}</a>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom: 20px;">
    <div class="stat">
        <div class="num">{{ $totalUsers }}</div>
        <div class="label">{{ __('Total Users') }} ({{ $activeUsers }} {{ __('active') }})</div>
    </div>
    <div class="stat">
        <div class="num">{{ $inactiveUsers + $trashedUsers }}</div>
        <div class="label">{{ __('Inactive / Soft-Deleted') }}</div>
    </div>
    <div class="stat">
        <div class="num">{{ $totalRoles }}</div>
        <div class="label">{{ __('Roles') }} ({{ $customRoles }} {{ __('custom') }})</div>
    </div>
    <div class="stat">
        <div class="num">{{ $totalStudents }}</div>
        <div class="label">{{ __('Students across :count classes', ['count' => $totalClasses]) }}</div>
    </div>
</div>

<div class="grid grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="flex-between" style="margin-bottom: 12px;">
            <h3>{{ __('Recent Users') }}</h3>
            <a href="{{ route('super-admin.users.index') }}" style="font-size: 13px;">{{ __('View all →') }}</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentUsers as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            <div style="font-size: 12px; color: var(--muted);">{{ $user->email }}</div>
                        </td>
                        <td><span class="badge badge-slate">{{ $user->roleRecord->name ?? __(ucfirst(str_replace('_', ' ', $user->role))) }}</span></td>
                        <td>
                            @if($user->trashed())
                                <span class="badge badge-red">{{ __('Deleted') }}</span>
                            @elseif($user->is_active)
                                <span class="badge badge-green">{{ __('Active') }}</span>
                            @else
                                <span class="badge badge-amber">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">{{ __('No users found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="flex-between" style="margin-bottom: 12px;">
            <h3>{{ __('System & Custom Roles') }}</h3>
            <a href="{{ route('super-admin.roles.index') }}" style="font-size: 13px;">{{ __('Manage roles →') }}</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Assigned Users') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentRoles as $role)
                    <tr>
                        <td>
                            <strong>{{ $role->name }}</strong>
                            <div style="font-size: 12px; color: var(--muted);">{{ $role->slug }}</div>
                        </td>
                        <td>
                            @if($role->is_system)
                                <span class="badge badge-primary">{{ __('System') }}</span>
                            @else
                                <span class="badge badge-violet">{{ __('Custom') }}</span>
                            @endif
                        </td>
                        <td>{{ $role->users_count }} {{ __('users') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">{{ __('No roles configured.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
