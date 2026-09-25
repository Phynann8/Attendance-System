@extends('layouts.app')

@section('title', __('My Permission Requests'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Permission Request') }}</div>
    </div>
    <a href="{{ route('parent.permissions.create') }}" class="btn">+ {{ __('Request Permission') }}</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('N.O') }}</th>
                <th>{{ __('Student') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Reason') }}</th>
                <th>{{ __('Requested at') }}</th>
                <th>{{ __('Status') }}</th>
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
                            <div class="small muted">{{ __('by') }} {{ $permission->approver->name }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">{{ __('No permission requests.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection