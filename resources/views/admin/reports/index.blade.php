@extends('layouts.app')

@section('title', 'Attendance Reports')

@section('content')
<div class="flex-between" style="flex-wrap:wrap; gap:12px; margin-bottom:16px;">
    <div>
        <div class="page-title">{{ __('Reports') }} — {{ __('Attendance Intelligence') }}</div>
        <div class="page-sub">
            @if($startDate->isSameDay($endDate))
                {{ $startDate->format('l, d M Y') }}
            @else
                {{ $startDate->format('d M Y') }} – {{ $endDate->format('d M Y') }}
            @endif
        </div>
    </div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <a href="{{ route('admin.reports.export', request()->query()) }}" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px;">
            <i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}
        </a>
    </div>
</div>

<!-- Filter card -->
<div class="card" style="margin-bottom:20px; padding:16px 20px;">
    <form method="GET" action="{{ route('admin.reports.index') }}" id="reportFilterForm">
        <div style="display:flex; gap:8px; align-items:center; margin-bottom:14px; flex-wrap:wrap;">
            <span style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-right:4px;">{{ __('Presets:') }}</span>
            <a href="{{ route('admin.reports.index', ['preset' => 'today']) }}" class="btn btn-sm {{ $preset === 'today' || (!$preset && $startDate->isToday() && $endDate->isToday()) ? 'btn-primary' : 'btn-outline' }}">
                {{ __('Today') }}
            </a>
            <a href="{{ route('admin.reports.index', ['preset' => 'this_week']) }}" class="btn btn-sm {{ $preset === 'this_week' ? 'btn-primary' : 'btn-outline' }}">
                {{ __('This Week') }}
            </a>
            <a href="{{ route('admin.reports.index', ['preset' => 'this_month']) }}" class="btn btn-sm {{ $preset === 'this_month' ? 'btn-primary' : 'btn-outline' }}">
                {{ __('This Month') }}
            </a>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label for="start_date" style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;">{{ __('Start Date') }}</label>
                <input type="date" id="start_date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" style="width:100%; padding:7px 10px; font-size:13px; border-radius:6px; border:1px solid #cbd5e1;">
            </div>

            <div class="form-group" style="margin:0;">
                <label for="end_date" style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;">{{ __('End Date') }}</label>
                <input type="date" id="end_date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" style="width:100%; padding:7px 10px; font-size:13px; border-radius:6px; border:1px solid #cbd5e1;">
            </div>

            @if(auth()->user()->isSuperAdmin() || !auth()->user()->campus_id)
                <div class="form-group" style="margin:0;">
                    <label for="campus_id" style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;">{{ __('Campus') }}</label>
                    <select id="campus_id" name="campus_id" style="width:100%; padding:7px 10px; font-size:13px; border-radius:6px; border:1px solid #cbd5e1;">
                        <option value="">{{ __('All Campuses') }}</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}" @selected(($campusId ?? request('campus_id')) == $campus->id)>
                                {{ $campus->code }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="form-group" style="margin:0;">
                <label for="class_id" style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;">{{ __('Class') }}</label>
                <select id="class_id" name="class_id" style="width:100%; padding:7px 10px; font-size:13px; border-radius:6px; border:1px solid #cbd5e1;">
                    <option value="">{{ __('All Classes') }}</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}" @selected($classId == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label for="final_status" style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;">{{ __('Final Status') }}</label>
                <select id="final_status" name="final_status" style="width:100%; padding:7px 10px; font-size:13px; border-radius:6px; border:1px solid #cbd5e1;">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="present" @selected($finalStatus === 'present')>{{ __('Present') }}</option>
                    <option value="late" @selected($finalStatus === 'late')>{{ __('Late') }}</option>
                    <option value="excused" @selected($finalStatus === 'excused')>{{ __('Excused (Permission)') }}</option>
                    <option value="absent_without_permission" @selected($finalStatus === 'absent_without_permission')>{{ __('Absent Without Permission') }}</option>
                    <option value="pending" @selected($finalStatus === 'pending')>{{ __('Pending Decision') }}</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary btn-sm" style="flex:1;">
                    <i class="fa-solid fa-filter"></i> {{ __('Apply Filters') }}
                </button>
                <a href="{{ route('admin.reports.index') }}" class="btn btn-outline btn-sm" title="{{ __('Reset Filters') }}">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Stat cards -->
<div class="grid grid-4" style="margin-bottom:20px;">
    <div class="stat"><div class="num text-green">{{ $summary['present'] }}</div><div class="label">{{ __('Present') }}</div></div>
    <div class="stat"><div class="num text-amber">{{ $summary['late'] }}</div><div class="label">{{ __('Late') }}</div></div>
    <div class="stat"><div class="num text-green">{{ $summary['excused'] }}</div><div class="label">{{ __('Excused') }}</div></div>
    <div class="stat"><div class="num text-red">{{ $summary['absent_without_permission'] }}</div><div class="label">{{ __('Unexcused') }}</div></div>
</div>

<div class="card">
    <div class="flex-between" style="margin-bottom:12px;">
        <h2 style="margin:0;">
            {{ __('Attendance Records') }} ({{ $rows->count() }})
        </h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('N.O') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Student') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Roll-Call') }}</th>
                <th>{{ __('Arrival') }}</th>
                <th>{{ __('Late (Min)') }}</th>
                <th>{{ __('Final Status') }}</th>
                <th>{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $attendance)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td style="white-space:nowrap;">{{ $attendance->session->session_date->format('Y-m-d') }}</td>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td>{{ $attendance->session->classRoom->name ?? '—' }}</td>
                    <td>
                        @if($attendance->status)
                            <x-status-badge :status="$attendance->status" />
                            @if($attendance->is_locked)
                                <span class="lock-icon" title="{{ __('Locked by Approved Permission') }}"><i class="fa-solid fa-lock"></i></span>
                            @endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $attendance->arrived_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $attendance->minutes_late ?? '—' }}</td>
                    <td>
                        @if($attendance->final_status)
                            <x-status-badge :status="$attendance->final_status" />
                        @else
                            <span class="badge">{{ __('Pending') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.attendance-sessions.show', $attendance->attendance_session_id) }}" class="btn btn-outline btn-sm" style="padding:3px 8px; font-size:11px;" title="{{ __('Inspect Session') }}">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Session') }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">{{ __('No attendance records match the specified criteria.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection