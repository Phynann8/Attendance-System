@extends('layouts.app')

@section('title', 'Review Absence')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ $attendance->student->name }}</div>
        <div class="page-sub">Final review — this student was absent and never arrived.</div>
    </div>
    <a href="{{ route('admin.absence.index') }}" class="btn btn-outline btn-sm">← Back to list</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Attendance timeline</h2>
        <table>
            <tr><th style="width:180px">Student</th><td>{{ $attendance->student->name }}</td></tr>
            <tr><th>Class</th><td>{{ $attendance->student->classRoom->name ?? '—' }}</td></tr>
            <tr><th>Parent contact</th>
                <td>
                    {{ $attendance->student->parent_name ?? '—' }}<br>
                    <span class="small muted">{{ $attendance->student->parent_phone ?? '' }} {{ $attendance->student->parent_email ?? '' }}</span>
                </td>
            </tr>
            <tr><th>Date</th><td>{{ $attendance->session->session_date->format('D, d M Y') }}</td></tr>
            <tr><th>Teacher attendance</th><td><x-status-badge status="absent" /></td></tr>
            <tr><th>Teacher submitted at</th><td>{{ $attendance->session->submitted_at?->format('H:i') ?? '—' }}</td></tr>
            <tr><th>Student Affairs</th><td><span class="badge badge-red">Did not arrive</span></td></tr>
            <tr><th>Permission</th>
                <td>
                    @if($attendance->permission)
                        <x-status-badge status="approved" label="Approved" />
                    @else
                        <span class="muted">None</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div>
        @if($existingPermissions->where('status', 'approved')->isNotEmpty())
            <div class="card">
                <h2>Existing approved permission found ✓</h2>
                @foreach($existingPermissions->where('status', 'approved') as $perm)
                    <div class="mb-2">
                        <strong>{{ $perm->reason }}</strong>
                        <div class="small muted">Approved {{ $perm->approved_at?->format('d M Y H:i') }}</div>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('admin.absence.decide', $attendance) }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="decision" value="excused">
                    <input type="hidden" name="permission_id" value="{{ $existingPermissions->where('status', 'approved')->first()->id }}">
                    <div class="form-group">
                        <label for="note_existing">Note</label>
                        <textarea id="note_existing" name="admin_note" rows="2">Reviewed against existing approved permission.</textarea>
                    </div>
                    <button type="submit" class="btn btn-green">✓ Excused Absence</button>
                </form>
            </div>
        @endif

        <div class="card">
            <h2>Contacted parent — valid reason</h2>
            <form method="POST" action="{{ route('admin.absence.decide', $attendance) }}">
                @csrf
                <input type="hidden" name="decision" value="excused">
                <div class="form-group">
                    <label for="requested_by">Requested by (parent) *</label>
                    <input id="requested_by" name="requested_by" placeholder="e.g. Sok Dara (parent)" value="{{ $attendance->student->parent_name }}">
                </div>
                <div class="form-group">
                    <label for="reason">Parent's reason *</label>
                    <input id="reason" name="reason" placeholder="e.g. My child is sick">
                </div>
                <div class="form-group">
                    <label for="note_valid">Note</label>
                    <textarea id="note_valid" name="admin_note" rows="2" placeholder="e.g. Called parent — reason verified as valid"></textarea>
                </div>
                <button type="submit" class="btn btn-green">✓ Permission Approved → Excused</button>
            </form>
        </div>

        <div class="card">
            <h2>Contacted parent — invalid reason</h2>
            <form method="POST" action="{{ route('admin.absence.decide', $attendance) }}">
                @csrf
                <input type="hidden" name="decision" value="absent_without_permission">
                <div class="form-group">
                    <label for="note_invalid">Note</label>
                    <textarea id="note_invalid" name="admin_note" rows="2" placeholder="e.g. Child did not want to come to school"></textarea>
                </div>
                <button type="submit" class="btn btn-red">✕ Absent Without Permission</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <h2>Audit log for this record</h2>
    <table>
        <thead>
            <tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr>
        </thead>
        <tbody>
            @forelse($attendance->logs ?? collect() as $log)
                <tr>
                    <td>{{ $log->created_at->format('d M H:i') }}</td>
                    <td>{{ $log->user->name ?? 'System' }}</td>
                    <td><code>{{ $log->action }}</code></td>
                    <td class="small muted">{{ $log->details }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No logs for this record yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection