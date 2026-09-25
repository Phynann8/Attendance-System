@extends('layouts.app')

@section('title', $student->name)

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">
            {{ $student->name }}
            @if($student->khmer_name)
                <span class="font-khmer" lang="km" style="font-weight: 500; font-size: 1.25rem; color: #475569; margin-left: 6px;">({{ $student->khmer_name }})</span>
            @endif
        </div>
        <div class="page-sub" style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
            @if($student->student_code)
                <span style="background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 600; color: #1e293b;">
                    <i class="fa-solid fa-id-card"></i> {{ $student->student_code }}
                </span>
            @endif
            <span>{{ __('Class') }} {{ $student->classRoom->name ?? '—' }}</span>
            @if($student->gender)
                <span>· {{ $student->gender === 'F' ? __('Female') : __('Male') }}</span>
            @endif
        </div>
    </div>
    <a href="{{ route('admin.students.index') }}" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> {{ __('Back') }}</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>{{ __('Profile Details') }}</h2>
        <table>
            @if($student->student_code)
                <tr><th style="width:160px">{{ __('Student ID') }}</th><td><code style="font-size:0.95rem; font-weight:600;">{{ $student->student_code }}</code></td></tr>
            @endif
            <tr><th style="width:160px">{{ __('English Name') }}</th><td><strong>{{ $student->name }}</strong></td></tr>
            @if($student->khmer_name)
                <tr><th>{{ __('Khmer Name') }}</th><td class="font-khmer" lang="km" style="color:#0f172a">{{ $student->khmer_name }}</td></tr>
            @endif
            @if($student->gender)
                <tr><th>{{ __('Gender') }}</th><td><span class="badge {{ $student->gender === 'F' ? 'badge-pink' : 'badge-blue' }} ">{{ $student->gender === 'F' ? __('Female') : __('Male') }}</span></td></tr>
            @endif
            @if($student->dob)
                <tr><th>{{ __('Date of Birth') }}</th><td>{{ $student->dob->format('d M Y') }}</td></tr>
            @endif
            <tr><th>{{ __('Class') }}</th><td><span class="badge badge-blue" style="font-weight: 600;">{{ $student->classRoom->name ?? '—' }}</span></td></tr>
            <tr><th>{{ __('Parent / Guardian') }}</th><td>{{ $student->parent_name ?? '—' }}</td></tr>
            <tr><th>{{ __('Parent Phone') }}</th><td>{{ $student->parent_phone ?? '—' }}</td></tr>
            <tr><th>{{ __('Parent Email') }}</th><td>{{ $student->parent_email ?? '—' }}</td></tr>
            <tr><th>{{ __('Status') }}</th><td>
                @if($student->is_active)
                    <span class="badge badge-green">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-red">{{ __('Inactive') }}</span>
                @endif
            </td></tr>
        </table>
    </div>

    <div class="card">
        <h2>{{ __('Permission History') }}</h2>
        <table>
            <thead>
                <tr><th>{{ __('Date') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    <tr>
                        <td>{{ $permission->attendance_date->format('d M Y') }}</td>
                        <td>{{ $permission->reason }}</td>
                        <td><x-status-badge :status="$permission->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">{{ __('No permissions.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>{{ __('Attendance History') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Teacher Mark') }}</th>
                <th>{{ __('Arrival') }}</th>
                <th>{{ __('Minutes Late') }}</th>
                <th>{{ __('Final Result') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>{{ $attendance->session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $attendance->session->classRoom->name ?? '—' }}</td>
                    <td>
                        <x-status-badge :status="$attendance->status" />
                        @if($attendance->is_locked) <span class="lock-icon" title="{{ __('Locked by Approved Permission') }}"><i class="fa-solid fa-lock"></i></span> @endif
                    </td>
                    <td>{{ $attendance->arrived_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $attendance->minutes_late ?? '—' }}</td>
                    <td>
                        @if($attendance->final_status)
                            <x-status-badge :status="$attendance->final_status" />
                        @else
                            <span class="muted">{{ __('Pending') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">{{ __('No attendance records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
