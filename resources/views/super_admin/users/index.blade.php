@extends('layouts.app')

@section('title', __('User Management'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('User Management') }}</div>
        <div class="page-sub">{{ __('Create, edit, inactivate, soft-delete users and assign roles.') }}</div>
    </div>
    <a href="{{ route('super-admin.users.create') }}" class="btn">+ {{ __('Add User') }}</a>
</div>

<div class="card">
    <form method="GET" class="filter-row">
        <div class="form-group search-filter">
            <label for="q">{{ __('Search') }}</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by name or email…') }}">
        </div>
        <div class="form-group">
            <label for="role_id">{{ __('Role') }}</label>
            <select id="role_id" name="role_id">
                <option value="">{{ __('All Roles') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="campus_id">{{ __('Campus') }}</label>
            <select id="campus_id" name="campus_id">
                <option value="">{{ __('All Campuses') }}</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected(request('campus_id') == $campus->id)>{{ $campus->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="status">{{ __('Status') }}</label>
            <select id="status" name="status">
                <option value="all" @selected(request('status', 'all') === 'all')>{{ __('All Statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active Only') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive Only') }}</option>
                <option value="trashed" @selected(request('status') === 'trashed')>{{ __('Soft-Deleted Only') }}</option>
            </select>
        </div>
        <button class="btn" type="submit" style="align-self: flex-end;">{{ __('Filter') }}</button>
        @if(request()->hasAny(['q', 'role_id', 'campus_id', 'status']))
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-secondary" style="align-self: flex-end;">{{ __('Reset') }}</a>
        @endif
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('N.O') }}</th>
                <th>{{ __('User Details') }}</th>
                <th>{{ __('Assigned Role') }}</th>
                <th>{{ __('Campus') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Created') }}</th>
                <th style="text-align: right;">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
                <tr style="{{ $u->trashed() ? 'opacity: 0.65; background: #fff1f2;' : '' }}">
                    <td>{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                    <td>
                        <strong>{{ $u->name }}</strong>
                        <div style="font-size: 13px; color: var(--muted);">{{ $u->email }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $u->role === 'super_admin' ? 'badge-violet' : ($u->role === 'admin' ? 'badge-primary' : 'badge-slate') }}">
                            {{ $u->roleRecord->name ?? __(ucfirst(str_replace('_', ' ', $u->role))) }}
                        </span>
                    </td>
                    <td>
                        @if($u->campus)
                            <span class="badge badge-slate" title="{{ $u->campus->name_en }} ({{ $u->campus->name_kh }})">
                                {{ $u->campus->code }}
                            </span>
                        @else
                            <span style="font-size: 13px; color: var(--muted);">{{ __('All / Central') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($u->trashed())
                            <span class="badge badge-red">{{ __('Deleted (Soft)') }}</span>
                        @elseif($u->is_active)
                            <span class="badge badge-green">{{ __('Active') }}</span>
                        @else
                            <span class="badge badge-amber">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td style="font-size: 13px; color: var(--muted);">
                        {{ $u->created_at?->format('M d, Y') ?? '—' }}
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                            @if($u->trashed())
                                <form method="POST" action="{{ route('super-admin.users.restore', $u->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-green">{{ __('Restore') }}</button>
                                </form>
                            @else
                                <a href="{{ route('super-admin.users.edit', $u->id) }}" class="btn btn-sm btn-secondary">{{ __('Edit') }}</a>

                                @if($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('super-admin.users.toggle-status', $u->id) }}">
                                        @csrf
                                        @if($u->is_active)
                                            <button type="submit" class="btn btn-sm btn-gold" title="{{ __('Deactivate this user') }}">{{ __('In-activate') }}</button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-green" title="{{ __('Activate this user') }}">{{ __('Activate') }}</button>
                                        @endif
                                    </form>

                                    <form method="POST" action="{{ route('super-admin.users.destroy', $u->id) }}" onsubmit="return confirm('{{ __('Inactivate and soft-delete user') }} \'{{ addslashes($u->name) }}\'?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-red">{{ __('Delete') }}</button>
                                    </form>
                                @else
                                    <span style="font-size: 12px; color: var(--muted); font-style: italic;">({{ __('Current') }})</span>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 24px; color: var(--muted);">
                        {{ __('No users found matching the filter criteria.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 16px;">
        {{ $users->links() }}
    </div>
</div>
@endsection
