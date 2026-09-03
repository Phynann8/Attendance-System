@extends('layouts.app')

@section('title', 'Teacher Dashboard')

@section('content')
<div class="page-title">Welcome, {{ auth()->user()->name }}</div>
<div class="page-sub">Please check student attendance on time. Permission students are locked.</div>

@forelse($classes as $class)
    <div class="card">
        <div class="flex-between">
            <div>
                <h2>{{ $class->name }} <span class="muted small">{{ $class->grade }}</span></h2>
                <div class="small muted">{{ $class->studentCount }} active student(s)</div>
            </div>

            @if($class->todaySession)
                @if($class->todaySession->status === 'open')
                    <a href="{{ route('teacher.attendance.mark', $class->todaySession) }}" class="btn btn-amber">Check today's attendance</a>
                @elseif($class->todaySession->status === 'submitted')
                    <a href="{{ route('teacher.attendance.mark', $class->todaySession) }}" class="btn btn-outline">View submitted attendance</a>
                @else
                    <span class="badge badge-slate">Closed</span>
                @endif
            @else
                <form method="POST" action="{{ route('teacher.attendance.open', $class) }}">
                    @csrf
                    <button type="submit" class="btn">Open Attendance for {{ now()->format('d M') }}</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card empty">No classes assigned to you yet.</div>
@endforelse
@endsection