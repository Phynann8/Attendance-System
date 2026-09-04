@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Daily Attendance Report</div>
        <div class="page-sub">Summary Attendance Report</div>
    </div>
    <form method="GET" class="flex-between" style="gap:8px">
        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" style="width:auto" onchange="this.form.submit()">
    </form>
</div>

<div class="grid grid-4">
    <div class="stat"><div class="num text-green">{{ $summary['present'] }}</div><div class="label">Present</div></div>
    <div class="stat"><div class="num text-amber">{{ $summary['late'] }}</div><div class="label">Late</div></div>
    <div class="stat"><div class="num text-green">{{ $summary['excused'] }}</div><div class="label">Excused</div></div>
    <div class="stat"><div class="num text-red">{{ $summary['absent_without_permission'] }}</div><div class="label">Absent Without Permission</div></div>
</div>

<div class="card">
    <h2>{{ $date->format('l, d M Y') }}</h2>
    <table>
        <thead>
            <tr>
                <th>N.O</th>
                <th>Student</th>
                <th>Class</th>
                <th>Teacher Attendance</th>
                <th>Arrival</th>
                <th>Minutes Late</th>
                <th>Final Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $attendance)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td>{{ $attendance->session->classRoom->name ?? '—' }}</td>
                    <td>
                        @if($attendance->status)
                            <x-status-badge :status="$attendance->status" />
                            @if($attendance->is_locked) <span class="lock-icon">🔒</span> @endif
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
                            <span class="badge">Pending</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No attendance on this date.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection