@extends('layouts.app')

@section('title', "Permission #{$permission->id}")

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Permission #{{ $permission->id }}</div>
        <div class="page-sub">
            <x-status-badge :status="$permission->status" />
            <span class="muted">— for {{ $permission->attendance_date->format('D, d M Y') }}</span>
        </div>
    </div>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline btn-sm">← Back</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Request details</h2>
        <table>
            <tr><th style="width:160px">Student</th><td>{{ $permission->student->name }}</td></tr>
            <tr><th>Class</th><td>{{ $permission->student->classRoom->name ?? '—' }}</td></tr>
            <tr><th>Date</th><td>{{ $permission->attendance_date->format('D, d M Y') }}</td></tr>
            <tr><th>Requested by</th><td>{{ $permission->requested_by }}
                <span class="small muted">({{ $permission->requested_by_type }})</span></td></tr>
            <tr><th>Reason</th><td>{{ $permission->reason }}</td></tr>
            <tr><th>Created</th><td>{{ $permission->created_at->format('d M Y H:i') }}</td></tr>
            @if($permission->evidence_path)
                <tr>
                    <th>Evidence</th>
                    <td><a href="{{ asset('storage/'.$permission->evidence_path) }}" target="_blank">View document ↗</a></td>
                </tr>
            @endif
            @if($permission->admin_note)
                <tr><th>Admin note</th><td>{{ $permission->admin_note }}</td></tr>
            @endif
        </table>
    </div>

    <div class="card">
        <h2>Decision</h2>

        @if($permission->status === 'approved')
            <div class="alert alert-success">
                Approved by {{ $permission->approver->name ?? '—' }} on
                {{ $permission->approved_at?->format('d M Y H:i') }}.
                <br>Teacher will see this student as <strong>🟢 PERMISSION 🔒</strong> (cannot modify).
            </div>
        @elseif($permission->status === 'rejected')
            <div class="alert alert-error">
                Rejected by {{ $permission->rejecter->name ?? '—' }} on
                {{ $permission->rejected_at?->format('d M Y H:i') }}.
            </div>
        @else
            @if($permission->attendance_date->isPast())
                <div class="alert alert-warning">This request is for a past date.</div>
            @endif

            <form method="POST" action="{{ route('admin.permissions.approve', $permission) }}">
                @csrf
                <div class="form-group">
                    <label for="approve_note">Note (optional)</label>
                    <textarea id="approve_note" name="admin_note" rows="2" placeholder="Evidence verified ✓"></textarea>
                </div>
                <button type="submit" class="btn btn-green">✓ Approve</button>
            </form>

            <form method="POST" action="{{ route('admin.permissions.reject', $permission) }}" class="mt-3">
                @csrf
                <div class="form-group">
                    <label for="reject_note">Rejection reason (optional)</label>
                    <textarea id="reject_note" name="admin_note" rows="2" placeholder="e.g. Missing evidence"></textarea>
                </div>
                <button type="submit" class="btn btn-red">✕ Reject</button>
            </form>
        @endif
    </div>
</div>
@endsection