@extends('layouts.app')

@section('title', __('Absence Review'))

@section('content')
<div class="page-title">{{ __('Absence Review') }}</div>
<div class="page-sub">
    {{ __('Please check students marked') }} <span class="badge badge-red">{{ __('Absent') }}</span>
    {{ __('by the teacher with Student Affairs for verification.') }}
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('N.O') }}</th>
                <th>{{ __('Student') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Teacher Attendance') }}</th>
                <th>{{ __('Student Affairs') }}</th>
                <th>{{ __('Existing Permission') }}</th>
                <th>{{ __('Review') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cases as $attendance)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td>{{ $attendance->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td><x-status-badge status="absent" /></td>
                    <td><span class="badge badge-red">{{ __('Did not arrive') }}</span></td>
                    <td>
                        @if($attendance->permission)
                            <x-status-badge status="approved" />
                        @else
                            <span class="muted">{{ __('None') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.absence.show', $attendance) }}" class="btn btn-sm btn-primary">{{ __('Review') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">{{ __('No absence cases awaiting your decision.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection