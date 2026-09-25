@extends('layouts.app')

@section('title', __('Parent Dashboard'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Parent Dashboard') }}</div>
    </div>
    <a href="{{ route('parent.permissions.create') }}" class="btn">+ {{ __('Request Permission') }}</a>
</div>

<div class="card">
    <h2>{{ __('Students Attendance') }}</h2>
    <table>
        <thead>
            <tr><th>{{ __('Student Name') }}</th><th>{{ __('Class') }}</th><th>{{ __('Attendance') }}</th><th>{{ __('Status') }}</th></tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                @php $today = $todayAttendance->firstWhere('student_id', $student->id); @endphp
                <tr>
                    <td><strong>{{ $student->name }}</strong></td>
                    <td>{{ $student->classRoom->name ?? '—' }}</td>
                    <td>
                        @if($today)
                            <x-status-badge :status="$today->status" />
                            @if($today->is_locked) <span class="lock-icon" title="{{ __('Locked by Approved Permission') }}"><i class="fa-solid fa-lock"></i></span> @endif
                        @else
                            <span class="muted">{{ __('No Record') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($today && $today->final_status)
                            <x-status-badge :status="$today->final_status" />
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">{{ __('No Students linked to this account.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2>{{ __('Permission Request History') }}</h2>
    <table>
        <thead>
            <tr><th>{{ __('Student Name') }}</th><th>{{ __('Date') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th></tr>
        </thead>
        <tbody>
            @forelse($permissions as $permission)
                <tr>
                    <td><strong>{{ $permission->student->name }}</strong></td>
                    <td>{{ $permission->attendance_date->format('D, d M Y') }}</td>
                    <td>{{ $permission->reason }}</td>
                    <td><x-status-badge :status="$permission->status" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">{{ __('No permission requests yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection