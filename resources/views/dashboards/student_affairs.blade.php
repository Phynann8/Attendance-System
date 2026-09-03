@extends('layouts.app')

@section('title', 'Student Affairs Dashboard')

@section('content')
<div class="page-title">Welcome, {{ auth()->user()->name }}</div>
<div class="page-sub">After assign late to student, Please verify absent student with Teacher before submit</div>

<div class="grid grid-4">
    <div class="stat"><div class="num">{{ $pendingCases->count() }}</div><div class="label">Absent Cases to Verify</div></div>
    <div class="stat"><div class="num">{{ $lateToday }}</div><div class="label">Late Cases Closed Today</div></div>
    <div class="stat"><div class="num">{{ $escalatedCount }}</div><div class="label">Escalated to Admin</div></div>
</div>

<div class="card mt-4">
    <h2>Latest absent students to check</h2>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Date</th>
                <th>Teacher</th>
                <th>Review</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingCases->take(10) as $attendance)
                <tr>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td>{{ $attendance->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $attendance->session->teacher->name }}</td>
                    <td><a href="{{ route('student-affairs.review.index') }}" class="btn btn-sm btn-outline">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No absent cases awaiting verification.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="mt-3"><a href="{{ route('student-affairs.review.index') }}" class="btn btn-sm btn-outline">Open full review list →</a></p>
</div>
@endsection