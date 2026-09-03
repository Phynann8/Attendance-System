@extends('layouts.app')

@section('title', 'Class Attendance')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Attendance — {{ $session->classRoom->name }}</div>
        <div class="page-sub">
            {{ $session->session_date->format('l, d M Y') }} ·
            Opened at {{ $session->opened_at?->format('H:i') ?? '—' }} ·
            Status: <x-status-badge :status="$session->status" />
        </div>
    </div>
    @if($session->status === 'submitted')
        <span class="badge badge-blue">Submitted {{ $session->submitted_at?->format('H:i') }}</span>
    @endif
</div>

@if($session->status === 'open')
    <div class="alert alert-info">
        <strong>NOTE:</strong> students with an approved permission show as
        <span class="badge badge-violet">PERMISSION 🔒</span> and <strong>cannot be changed</strong>.
        You can only put <strong>Present</strong> or <strong>Absent</strong>.
    </div>

    <form method="POST" action="{{ route('teacher.attendance.save', $session) }}" id="markForm">
        @csrf
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Status</th>
                    <th style="width:280px">Teacher Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($session->attendances->sortBy(fn($a) => $a->student->name) as $attendance)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $attendance->student->name }}</strong></td>
                        <td>
                            @if($attendance->is_locked)
                                <span class="badge badge-violet">🟢 PERMISSION 🔒</span>
                            @elseif($attendance->status)
                                <x-status-badge :status="$attendance->status" />
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($attendance->is_locked)
                                <div class="small muted">
                                    {{ $attendance->lock_reason }}
                                    @if($attendance->permission && $attendance->permission->reason)
                                        — <em>{{ $attendance->permission->reason }}</em>
                                    @endif
                                </div>
                            @else
                                <div>
                                    <label style="display:inline-flex; align-items:center; gap:4px; margin-right:12px; cursor:pointer">
                                        <input type="radio" name="statuses[{{ $attendance->student_id }}]" value="present"
                                               style="width:auto"
                                               @checked($attendance->status === 'present')>
                                        Present
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer">
                                        <input type="radio" name="statuses[{{ $attendance->student_id }}]" value="absent"
                                               style="width:auto"
                                               @checked($attendance->status === 'absent')>
                                        Absent
                                    </label>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </form>

    <div class="card flex-between">
        <div class="muted small">
            Please verify Student attendance before submit.
        </div>
        <div>
            <button type="submit" form="markForm" class="btn">Save Attendance</button>
            <button type="submit" form="submitForm" class="btn btn-green"
                    onclick="return confirm('Submit attendance? This locks attendance & Cannot be changed again')">
                 Submit Attendance
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('teacher.attendance.submit', $session) }}" id="submitForm">
        @csrf
    </form>
@else
    @include('teacher.attendance._submitted_table', ['session' => $session])
@endif
@endsection