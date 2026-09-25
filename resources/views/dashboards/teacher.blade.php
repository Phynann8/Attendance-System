@extends('layouts.app')

@section('title', __('Teacher Dashboard'))

@section('content')
<div class="page-title">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</div>
<div class="page-sub">{{ __('Please check student attendance on time. Permission students are locked.') }}</div>

@forelse($classes as $class)
    <div class="card">
        <div class="flex-between">
            <div>
                <h2>{{ $class->name }} <span class="muted small">{{ $class->grade }}</span></h2>
                <div class="small muted">{{ __(':count active student(s)', ['count' => $class->studentCount]) }}</div>
            </div>

            @if($class->todaySession)
                @if($class->todaySession->status === 'open')
                    <a href="{{ route('teacher.attendance.mark', $class->todaySession) }}" class="btn btn-amber">{{ __("Check today's attendance") }}</a>
                @elseif($class->todaySession->status === 'submitted')
                    <a href="{{ route('teacher.attendance.mark', $class->todaySession) }}" class="btn btn-green">{{ __('View submitted attendance') }}</a>
                @else
                    <span class="badge badge-slate">{{ __('Closed') }}</span>
                @endif
            @else
                <form method="POST" action="{{ route('teacher.attendance.open', $class) }}">
                    @csrf
                    <button type="submit" class="btn">{{ __('Open Attendance for :date', ['date' => now()->format('d M')]) }}</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card empty">{{ __('No classes assigned to you yet.') }}</div>
@endforelse
@endsection