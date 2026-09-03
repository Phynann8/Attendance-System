@extends('layouts.app')

@section('title', 'Attendance History')

@section('content')
<div class="page-title">Attendance Sessions</div>
<div class="page-sub">Your classes and their attendance sessions.</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Class</th>
                <th>Date</th>
                <th>Opened</th>
                <th>Submitted</th>
                <th>Students</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td><strong>{{ $session->classRoom->name }}</strong></td>
                    <td>{{ $session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $session->opened_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $session->submitted_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $session->attendances->count() }}</td>
                    <td><x-status-badge :status="$session->status" /></td>
                    <td>
                        <a href="{{ route('teacher.attendance.mark', $session) }}" class="btn btn-sm btn-outline">
                            {{ $session->status === 'open' ? 'Continue marking' : 'View' }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No attendance sessions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection