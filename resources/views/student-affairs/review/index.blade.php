@extends('layouts.app')

@section('title', 'Absence Review')

@section('content')
<div class="page-title">Absence Verification</div>
<div class="page-sub">
    Please Verify<span class="badge badge-red">Absent</span> Student with Teacher.
</div>

<div class="grid grid-4">
    <div class="stat"><div class="num">{{ $cases->count() }}</div><div class="label">Cases to Review</div></div>
    <div class="stat"><div class="num text-amber">{{ $lateToday }}</div><div class="label">Late Cases Closed Today</div></div>
    <div class="stat"><div class="num text-red">{{ $escalatedCount }}</div><div class="label">Escalated to Admin</div></div>
</div>

<div class="card mt-4">
    <table>
        <thead>
            <tr>
                <th style="width:auto;">Student</th>
                <th style="text-align: center;">Class</th>
                <th>Date</th>
                <th>Teacher Submitted at</th>
                <th>Parent Contact</th>
                <th style="width:300px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cases as $attendance)
                <tr>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td style="text-align: center;">{{ $attendance->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $attendance->session->submitted_at?->format('H:i') ?? '—' }}</td>
                    <td class="small">
                        {{ $attendance->student->parent_phone ?? 'no phone' }}<br>
                        <span class="muted">{{ $attendance->student->parent_name ?? '' }}</span>
                    </td>
                    <td>
                        <div class="flex-between" style="flex-wrap:nowrap">
                            <form method="POST"
                                  action="{{ route('student-affairs.review.arrived', $attendance) }}"
                                  style="display:flex; gap:6px; align-items:center; flex:1">
                                @csrf
                                <input type="datetime-local" name="arrived_at"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       style="width:auto; padding:5px 8px; font-size:13px" required>
                                <button type="submit" class="btn btn-sm btn-green">LATE</button>
                            </form>
                            <form method="POST" action="{{ route('student-affairs.review.escalate', $attendance) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-red"
                                        onclick="return confirm('Confirm the student never arrived? Escalates to Admin.')">
                                    Absent
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No absent cases to verify. 🎉</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection