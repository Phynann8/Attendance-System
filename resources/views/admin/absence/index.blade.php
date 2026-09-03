@extends('layouts.app')

@section('title', 'Absence Review')

@section('content')
<div class="page-title">Absence Review</div>
<div class="page-sub">
    Students marked <span class="badge badge-red">Absent</span> by the teacher who never arrived —
    Student Affairs escalated them here. You make the final decision (Rule 6/11-14).
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Date</th>
                <th>Teacher Attendance</th>
                <th>Student Affairs</th>
                <th>Existing Permission</th>
                <th>Review</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cases as $attendance)
                <tr>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td>{{ $attendance->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td><x-status-badge status="absent" /></td>
                    <td><span class="badge badge-red">Did not arrive</span></td>
                    <td>
                        @if($attendance->permission)
                            <x-status-badge status="approved" />
                        @else
                            <span class="muted">None</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.absence.show', $attendance) }}" class="btn btn-sm btn-hover:btn-primary">Review</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No absence cases awaiting your decision.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection