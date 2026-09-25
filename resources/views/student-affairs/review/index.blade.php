@extends('layouts.app')

@section('title', __('Absence Verification'))

@section('content')
<div class="page-title">{{ __('Absence Verification') }}</div>
<div class="page-sub">
    {{ __('Please verify') }} <span class="badge badge-red">{{ __('Absent') }}</span> {{ __('student with the teacher.') }}
</div>

<div class="grid grid-4">
    <div class="stat"><div class="num">{{ $cases->count() }}</div><div class="label">{{ __('Cases to Review') }}</div></div>
    <div class="stat"><div class="num text-amber">{{ $lateToday }}</div><div class="label">{{ __('Late Cases Closed Today') }}</div></div>
    <div class="stat"><div class="num text-red">{{ $escalatedCount }}</div><div class="label">{{ __('Escalated to Admin') }}</div></div>
</div>

<div class="card mt-4">
    <table>
        <thead>
            <tr>
                <th style="width:auto;">{{ __('Student') }}</th>
                <th style="text-align: center;">{{ __('Class') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Teacher Submitted at') }}</th>
                <th>{{ __('Parent Contact') }}</th>
                <th style="width:300px">{{ __('Actions') }}</th>
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
                        {{ $attendance->student->parent_phone ?? __('no phone') }}<br>
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
                                <button type="submit" class="btn btn-sm btn-green">{{ __('LATE') }}</button>
                            </form>
                            <form method="POST" action="{{ route('student-affairs.review.escalate', $attendance) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-red"
                                        onclick="return confirm('{{ __('Confirm the student never arrived? Escalates to Admin.') }}')">
                                    {{ __('Absent') }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">{{ __('No absent cases to verify. 🎉') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection