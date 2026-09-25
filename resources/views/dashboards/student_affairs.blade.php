@extends('layouts.app')

@section('title', __('Student Affairs Dashboard'))

@section('content')
<div class="page-title">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</div>
<div class="page-sub">{{ __('After assign late to student, Please verify absent student with Teacher before submit') }}</div>

<div class="grid grid-4">
    <div class="stat"><div class="num">{{ $pendingCases->count() }}</div><div class="label">{{ __('Absent Cases to Verify') }}</div></div>
    <div class="stat"><div class="num">{{ $lateToday }}</div><div class="label">{{ __('Late Cases Closed Today') }}</div></div>
    <div class="stat"><div class="num">{{ $escalatedCount }}</div><div class="label">{{ __('Escalated to Admin') }}</div></div>
</div>

<div class="card mt-4">
    <h2>{{ __('Latest absent students to check') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Student') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Teacher') }}</th>
                <th>{{ __('Review') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingCases->take(10) as $attendance)
                <tr>
                    <td><strong>{{ $attendance->student->name }}</strong></td>
                    <td>{{ $attendance->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $attendance->session->teacher->name }}</td>
                    <td><a href="{{ route('student-affairs.review.index') }}" class="btn btn-sm btn-outline">{{ __('Review') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ __('No absent cases awaiting verification.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="mt-3"><a href="{{ route('student-affairs.review.index') }}" class="btn btn-sm btn-outline">{{ __('Open full review list →') }}</a></p>
</div>
@endsection