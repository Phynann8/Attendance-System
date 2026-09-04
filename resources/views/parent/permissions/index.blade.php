@extends('layouts.app')

@section('title', 'My Permission Requests')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Permission Request</div>
    </div>
    <a href="{{ route('parent.permissions.create') }}" class="btn">+ Request Permission</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>N.O</th>
                <th>Student</th>
                <th>Class</th>
                <th>Date</th>
                <th>Reason</th>
                <th>Requested at</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($permissions as $permission)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $permission->student->name }}</strong></td>
                    <td>{{ $permission->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $permission->attendance_date->format('D, d M Y') }}</td>
                    <td>{{ $permission->reason }}</td>
                    <td>{{ $permission->created_at->format('d M Y H:i') }}</td>
                    <td>
                        <x-status-badge :status="$permission->status" />
                        @if($permission->status === 'approved' && $permission->approver)
                            <div class="small muted">by {{ $permission->approver->name }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No permission requests.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection