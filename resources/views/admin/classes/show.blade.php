@extends('layouts.app')

@section('title', $class->name)

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Class {{ $class->name }}</div>
        <div class="page-sub">
            {{ $class->grade ?? '—' }} · Teacher: {{ $class->teacher->name ?? '—' }}
        </div>
    </div>
    <a href="{{ route('admin.classes.index') }}" class="btn btn-outline btn-sm">← Back</a>
</div>

<div class="card">
    <h2>Students ({{ $class->students->count() }})</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Student</th><th>Parent</th><th></th></tr>
        </thead>
        <tbody>
            @forelse($class->students as $student)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $student->name }}</strong></td>
                    <td>{{ $student->parent_name ?? '—' }}</td>
                    <td><a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline">View</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No students in this class yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Attendance sessions</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Opened</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Permission</th>
                <th>Late</th>
                <th>Excused</th>
                <th>AWP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $session)
                @php
                    $stats = $session->attendances->groupBy('final_status')->map->count();
                @endphp
                <tr>
                    <td>{{ $session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $session->opened_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $session->submitted_at?->format('H:i') ?? '—' }}</td>
                    <td><x-status-badge :status="$session->status" /></td>
                    <td class="text-green">{{ $stats['present'] ?? 0 }}</td>
                    <td>{{ $stats['absent_without_permission'] ?? 0 }}</td>
                    <td>{{ $session->attendances->where('status', 'permission')->count() }}</td>
                    <td class="text-amber">{{ $stats['late'] ?? 0 }}</td>
                    <td class="text-green">{{ $stats['excused'] ?? 0 }}</td>
                    <td>{{ $session->attendances->whereNull('final_status')->count() }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No attendance sessions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection