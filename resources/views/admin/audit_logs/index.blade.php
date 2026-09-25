@extends('layouts.app')

@section('title', __('Audit Log History'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('System Audit Log History') }}</div>
        <div class="page-sub">{{ __('Tamper-evident trail of all attendance mark submissions, permission approvals, and administrative decisions.') }}</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="filter-row">
        <div class="form-group">
            <label for="action">{{ __('Action Type') }}</label>
            <select id="action" name="action">
                <option value="">{{ __('All Actions') }}</option>
                @foreach($actionTypes as $type)
                    <option value="{{ $type }}" @selected(request('action') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="date">{{ __('Date') }}</label>
            <input type="date" id="date" name="date" value="{{ request('date') }}">
        </div>
        <div class="form-group search-filter">
            <label for="q">{{ __('Search') }}</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Search actor or details…') }}">
        </div>
        <button class="btn" type="submit" style="align-self: flex-end;">{{ __('Filter') }}</button>
        @if(request()->hasAny(['action', 'date', 'q']))
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary" style="align-self: flex-end;">{{ __('Reset') }}</a>
        @endif
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th style="width: 140px;">{{ __('Timestamp') }}</th>
                <th>{{ __('Action') }}</th>
                <th>{{ __('Actor') }}</th>
                <th>{{ __('Related Record') }}</th>
                <th>{{ __('Details') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                @php
                    $action = $log->action;
                    $badgeClass = match (true) {
                        str_contains($action, 'approved') || str_contains($action, 'submitted') => 'badge-green',
                        str_contains($action, 'rejected') || str_contains($action, 'absent_without_permission') => 'badge-red',
                        str_contains($action, 'late') || str_contains($action, 'escalated') => 'badge-amber',
                        str_contains($action, 'locked') => 'badge-violet',
                        default => 'badge-blue',
                    };
                @endphp
                <tr>
                    <td style="font-size: 13px; color: var(--muted); white-space: nowrap;">
                        <strong>{{ $log->created_at->format('M d, H:i:s') }}</strong>
                        <div style="font-size: 11px;">{{ $log->created_at->diffForHumans() }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $log->action }}</span>
                    </td>
                    <td>
                        @if($log->user)
                            <strong>{{ $log->user->name }}</strong>
                            <div style="font-size: 12px; color: var(--muted);">{{ __(ucfirst(str_replace('_', ' ', $log->user->role))) }}</div>
                        @else
                            <span style="color: var(--muted); font-style: italic;">{{ __('System / Automated') }}</span>
                        @endif
                    </td>
                    <td style="font-size: 13px;">
                        @if($log->attendance)
                            <div><strong>{{ __('Student') }}:</strong> {{ $log->attendance->student->name ?? '#' . $log->attendance->student_id }}</div>
                            @if($log->attendance->session?->classRoom)
                                <div style="color: var(--muted);">{{ __('Class') }}: {{ $log->attendance->session->classRoom->name }}</div>
                            @endif
                        @elseif($log->permission)
                            <div><strong>{{ __('Permission') }}:</strong> #{{ $log->permission->id }}</div>
                            @if($log->permission->student)
                                <div style="color: var(--muted);">{{ __('Student') }}: {{ $log->permission->student->name }}</div>
                            @endif
                        @else
                            <span style="color: var(--muted);">—</span>
                        @endif
                    </td>
                    <td style="font-size: 12px; max-width: 320px; word-break: break-all; color: #334155;">
                        {{ $log->details ?: '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 24px; color: var(--muted);">
                        {{ __('No audit records found matching the query.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 16px;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
