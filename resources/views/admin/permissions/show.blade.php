@extends('layouts.app')

@section('title', "Permission #{$permission->id}")

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Permission') }} #{{ $permission->id }}</div>
        <div class="page-sub">
            <x-status-badge :status="$permission->status" />
            <span class="muted">— {{ __('for') }} {{ $permission->attendance_date->format('D, d M Y') }}</span>
        </div>
    </div>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline btn-sm">← {{ __('Back') }}</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>{{ __('Request details') }}</h2>
        <table>
            <tr><th style="width:160px">{{ __('Student') }}</th><td>{{ $permission->student->name }}</td></tr>
            <tr><th>{{ __('Class') }}</th><td>{{ $permission->student->classRoom->name ?? '—' }}</td></tr>
            <tr><th>{{ __('Date') }}</th><td>{{ $permission->attendance_date->format('D, d M Y') }}</td></tr>
            <tr><th>{{ __('Requested by') }}</th><td>{{ $permission->requested_by }}
                <span class="small muted">({{ $permission->requested_by_type }})</span></td></tr>
            <tr><th>{{ __('Category') }}</th><td><span class="badge badge-info">{{ \App\Models\Permission::categories()[$permission->category] ?? __(ucfirst($permission->category ?? 'Other')) }}</span></td></tr>
            <tr><th>{{ __('Reason') }}</th><td>{{ $permission->reason }}</td></tr>
            @if($permission->detail_description)
                <tr><th>{{ __('Detail Description') }}</th><td>{{ $permission->detail_description }}</td></tr>
            @endif
            <tr><th>{{ __('Created') }}</th><td>{{ $permission->created_at->format('d M Y H:i') }}</td></tr>
            @if($permission->evidence_path)
                <tr>
                    <th>{{ __('Evidence') }}</th>
                    <td><a href="{{ asset('storage/'.$permission->evidence_path) }}" target="_blank">{{ __('View document ↗') }}</a></td>
                </tr>
            @endif
            @if($permission->admin_note)
                <tr><th>{{ __('Admin note') }}</th><td>{{ $permission->admin_note }}</td></tr>
            @endif
        </table>
    </div>

    <div class="card">
        <h2>{{ __('Decision') }}</h2>

        @if($permission->status === 'approved')
            <div class="alert alert-success">
                {{ __('Approved by :name on :date.', ['name' => $permission->approver->name ?? '—', 'date' => $permission->approved_at?->format('d M Y H:i')]) }}
                <br>{{ __('Teacher will see this student as') }} <span class="badge badge-violet"><i class="fa-solid fa-lock"></i> {{ __('PERMISSION') }}</span> {{ __('(cannot modify).') }}
            </div>
        @elseif($permission->status === 'rejected')
            <div class="alert alert-error">
                {{ __('Rejected by :name on :date.', ['name' => $permission->rejecter->name ?? '—', 'date' => $permission->rejected_at?->format('d M Y H:i')]) }}
            </div>
        @else
            @if($permission->attendance_date->isPast())
                <div class="alert alert-warning">{{ __('This request is for a past date.') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.permissions.approve', $permission) }}">
                @csrf
                <div class="form-group">
                    <label for="approve_note">{{ __('Note (optional)') }}</label>
                    <textarea id="approve_note" name="admin_note" rows="2" placeholder="{{ __('Evidence verified ✓') }}"></textarea>
                </div>
                <button type="submit" class="btn btn-green">✓ {{ __('Approve') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.permissions.reject', $permission) }}" class="mt-3">
                @csrf
                <div class="form-group">
                    <label for="reject_note">{{ __('Rejection reason (optional)') }}</label>
                    <textarea id="reject_note" name="admin_note" rows="2" placeholder="{{ __('e.g. Missing evidence') }}"></textarea>
                </div>
                <button type="submit" class="btn btn-red">✕ {{ __('Reject') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection