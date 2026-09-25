@extends('layouts.app')

@section('title', __('Classes'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Classes') }}</div>
        <div class="page-sub">{{ __('Manage classrooms') }}</div>
    </div>
    <a href="{{ route('admin.classes.create') }}" class="btn">+ {{ __('Add Class') }}</a>
</div>

@if(auth()->user()->isSuperAdmin() && !session('active_campus_id'))
    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" class="filter-row" style="display: flex; gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="campus_id">{{ __('Filter by Campus') }}</label>
                <select id="campus_id" name="campus_id" onchange="this.form.submit()">
                    <option value="">{{ __('All Campuses (4)') }}</option>
                    @foreach($campuses as $camp)
                        <option value="{{ $camp->id }}" @selected(request('campus_id') == $camp->id)>
                            {{ $camp->code }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(request('campus_id'))
                <a href="{{ route('admin.classes.index') }}" class="btn btn-outline btn-sm">{{ __('Clear') }}</a>
            @endif
        </form>
    </div>
@endif

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('N.O') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Campus') }}</th>
                <th>{{ __('Grade') }}</th>
                <th>{{ __('Teacher') }}</th>
                <th>{{ __('Students') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($classes as $class)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $class->name }}</strong></td>
                    <td>
                        @if($class->campus)
                            <span class="badge badge-slate" style="font-weight: 700;">{{ $class->campus->code }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $class->grade ?? '—' }}</td>
                    <td>{{ $class->teacher->name ?? '—' }}</td>
                    <td><span class="badge badge-blue" style="font-weight: 600;">{{ $class->students_count }}</span></td>
                    <td><a href="{{ route('admin.classes.show', $class) }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-eye"></i> {{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">{{ __('No classes yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection