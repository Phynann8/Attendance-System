@extends('layouts.app')

@section('title', $student->name)

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ $student->name }}</div>
        <div class="page-sub">Class {{ $student->classRoom->name ?? '—' }}</div>
    </div>
    <a href="{{ route('admin.students.index') }}" class="btn btn-outline btn-primary">← Back</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Profile</h2>
        <table>
            <tr><th style="width:160px">Parent</th><td>{{ $student->parent_name ?? '—' }}</td></tr>
            <tr><th>Phone</th><td>{{ $student->parent_phone ?? '—' }}</td></tr>
            <tr><th>Email</th><td>{{ $student->parent_email ?? '—' }}</td></tr>
            <tr><th>Status</th><td>{{ $student->is_active ? 'Active' : 'Inactive' }}</td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Permission history</h2>
        <table>
            <thead>
                <tr><th>Date</th><th>Reason</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    <tr>
                        <td>{{ $permission->attendance_date->format('d M Y') }}</td>
                        <td>{{ $permission->reason }}</td>
                        <td><x-status-badge :status="$permission->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No permissions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Attendance history</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Class</th>
                <th>Teacher Mark</th>
                <th>Arrival</th>
                <th>Minutes Late</th>
                <th>Final Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $attendance->session->classRoom->name ?? '—' }}</td>
                    <td>
                        <x-status-badge :status="$attendance->status" />
                        @if($attendance->is_locked) <span class="lock-icon">🔒</span> @endif
                    </td>
                    <td>{{ $attendance->arrived_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $attendance->minutes_late ?? '—' }}</td>
                    <td>
                        @if($attendance->final_status)
                            <x-status-badge :status="$attendance->final_status" />
                        @else
                            <span class="muted">pending</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No attendance records.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection