@extends('layouts.app')

@section('title', __('Classes'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Classes') }}</div>
        <div class="page-sub">{{ __('Manage classrooms') }}</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-primary" onclick="openTimetableImportModal()" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-file-csv"></i>
            <span>{{ __('Import Timetable CSV') }}</span>
        </button>
    </div>
</div>

@if((auth()->user()->isSuperAdmin() || count(auth()->user()->assignedCampusIds()) > 1) && !session('active_campus_id'))
    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" class="filter-row" style="display: flex; gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="campus_id">{{ __('Filter by Campus') }}</label>
                <select id="campus_id" name="campus_id" onchange="this.form.submit()">
                    <option value="">{{ auth()->user()->isSuperAdmin() ? __('All Campuses') : __('All Assigned Campuses') }} ({{ $campuses->count() }})</option>
                    @foreach($campuses as $camp)
                        <option value="{{ $camp->id }}" @selected(request('campus_id') == $camp->id)>
                            {{ $camp->name }} ({{ $camp->code }})
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
                    <td>
                        <form method="POST" action="{{ route('admin.classes.assign-teacher', $class) }}" style="margin: 0; display: inline-flex; align-items: center; gap: 4px;">
                            @csrf
                            <div style="position: relative; display: inline-flex; align-items: center;">
                                <select name="teacher_id" onchange="this.form.submit()"
                                        style="padding: 4px 24px 4px 8px; font-size: 12px; border-radius: 6px; border: 1px solid {{ $class->teacher_id ? '#cbd5e1' : '#f59e0b' }}; background: {{ $class->teacher_id ? '#fff' : '#fffbeb' }}; cursor: pointer; color: {{ $class->teacher_id ? '#0f172a' : '#b45309' }}; font-weight: 500; appearance: none; -webkit-appearance: none; -moz-appearance: none; max-width: 190px;"
                                        title="{{ __('Select to assign or change homeroom teacher') }}">
                                    <option value="" style="color: #94a3b8;">{{ __('— Unassigned —') }}</option>
                                    @foreach($teachers as $teacher)
                                        @if(!$class->campus_id || $teacher->hasCampusAccess($class->campus_id))
                                            <option value="{{ $teacher->id }}" @selected($class->teacher_id == $teacher->id)>
                                                {{ $teacher->name }}
                                                @if($teacher->campuses->isNotEmpty() && (!session('active_campus_id') && auth()->user()->isSuperAdmin()))
                                                    ({{ $teacher->campuses->pluck('code')->join(', ') }})
                                                @elseif($teacher->campus && (!session('active_campus_id') && auth()->user()->isSuperAdmin()))
                                                    ({{ $teacher->campus->code }})
                                                @endif
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position: absolute; right: 8px; font-size: 9px; color: #94a3b8; pointer-events: none;"></i>
                            </div>
                        </form>
                    </td>
                    <td><span class="badge badge-blue" style="font-weight: 600;">{{ $class->students_count }}</span></td>
                    <td style="white-space: nowrap; text-align: right;"><a href="{{ route('admin.classes.show', $class) }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-eye"></i> {{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">{{ __('No classes yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Timetable CSV Import Modal (Story 39) -->
<div id="timetableImportModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal-card" style="background: #ffffff; border-radius: 12px; width: 92%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); border: 1px solid #e2e8f0;">
        <div class="flex-between" style="margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-calendar-week" style="color: var(--brand-blue, #0284c7);"></i>
                {{ __('Import Weekly Timetables') }}
            </h3>
            <button type="button" onclick="closeTimetableImportModal()" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">&times;</button>
        </div>
        <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 16px;">
            {{ __('Upload a CSV file containing weekly period schedules for classrooms. Existing period slots for imported classes will be updated or appended.') }}
        </p>
        <div style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 12px; color: #475569;">
            <div style="font-weight: 600; margin-bottom: 4px;">{{ __('Required CSV Columns') }}:</div>
            <code style="word-break: break-all;">class_name, day_of_week, period_number, subject, start_time, end_time, teacher_email, is_primary</code>
            <div style="margin-top: 10px;">
                <a href="{{ route('admin.schedules.template') }}" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-download"></i> {{ __('Download Sample CSV Template') }}
                </a>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.schedules.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group" style="margin-bottom: 16px;">
                <label for="csv_file">{{ __('Select CSV File') }} <span class="required">*</span></label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                    <input type="checkbox" name="truncate" value="1" style="width: auto;">
                    <span>{{ __('Overwrite: Clear existing period schedules for classes in CSV') }}</span>
                </label>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeTimetableImportModal()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-upload"></i> {{ __('Upload & Import') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openTimetableImportModal() {
    const m = document.getElementById('timetableImportModal');
    if (m) { m.style.display = 'flex'; }
}
function closeTimetableImportModal() {
    const m = document.getElementById('timetableImportModal');
    if (m) { m.style.display = 'none'; }
}
</script>
@endsection