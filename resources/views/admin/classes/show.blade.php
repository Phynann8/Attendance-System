@extends('layouts.app')

@section('title', $class->name)

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Class') }} {{ $class->name }}</div>
        <div class="page-sub">
            {{ $class->grade ?? '—' }} · {{ __('Teacher') }}: {{ $class->teacher->name ?? '—' }}
        </div>
    </div>
    <a href="{{ route('admin.classes.index') }}" class="btn btn-primary btn-sm">← {{ __('Back') }}</a>
</div>

<div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--brand-blue, #0284c7);">
    <div class="flex-between" style="flex-wrap: wrap; gap: 16px; align-items: center;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <div>
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                    {{ __('Homeroom Teacher') }}
                </div>
                <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    @if($class->teacher)
                        <span>{{ $class->teacher->name }}</span>
                        @if($class->teacher->khmer_name)
                            <span class="font-khmer" lang="km" style="font-weight: 500; color: #475569; font-size: 14px; margin-left: 4px;">({{ $class->teacher->khmer_name }})</span>
                        @endif
                        <span class="badge badge-green" style="font-size: 11px; margin-left: 6px; font-weight: 600;">
                            <i class="fa-solid fa-check"></i> {{ __('Assigned') }}
                        </span>
                    @else
                        <span class="text-muted" style="font-style: italic;">{{ __('No homeroom teacher assigned') }}</span>
                        <span class="badge badge-amber" style="font-size: 11px; margin-left: 6px;">{{ __('Unassigned') }}</span>
                    @endif
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    @if($class->teacher)
                        <i class="fa-solid fa-envelope" style="font-size: 11px;"></i> {{ $class->teacher->email }}
                        @if($class->teacher->phone)
                            · <i class="fa-solid fa-phone" style="font-size: 11px;"></i> {{ $class->teacher->phone }}
                        @endif
                    @else
                        {{ __('Assign an active teacher to authorize them to open sessions, mark attendance, and manage this class.') }}
                    @endif
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.classes.assign-teacher', $class) }}" style="display: flex; gap: 8px; align-items: center; margin: 0; flex-wrap: wrap;">
            @csrf
            <div style="position: relative;">
                <select name="teacher_id" style="min-width: 240px; padding: 7px 30px 7px 10px; font-size: 13px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #1e293b; font-weight: 500; cursor: pointer;">
                    <option value="">{{ __('— Select Active Teacher —') }}</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected($class->teacher_id == $teacher->id)>
                            {{ $teacher->name }} ({{ $teacher->email }})
                        </option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 11px; color: #94a3b8; pointer-events: none;"></i>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;">
                <i class="fa-solid fa-user-check"></i>
                <span>{{ $class->teacher_id ? __('Update Teacher') : __('Assign Teacher') }}</span>
            </button>
            @if($class->teacher_id)
                <button type="button" class="btn btn-outline btn-sm text-red" onclick="if(confirm('{{ __('Remove homeroom teacher from this class?') }}')) { document.querySelector('select[name=teacher_id]').value = ''; this.form.submit(); }" style="padding: 8px 12px;" title="{{ __('Unassign Teacher') }}">
                    <i class="fa-solid fa-user-xmark"></i>
                </button>
            @endif
        </form>
    </div>
</div>

<div class="card" style="margin-bottom: 20px;">
    @php
        $classHasSaturday = $class->activeSchedules->contains(fn($s) => $s->day_of_week === 6);
        $classDays = [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
        ];
        if ($classHasSaturday) {
            $classDays[6] = __('Saturday');
        }
        $classMaxPeriod = max($class->activeSchedules->max('period_number') ?? 0, 5);
        $classSchedulesMap = $class->activeSchedules->keyBy(fn($s) => $s->day_of_week . '_' . $s->period_number);
    @endphp

    <div class="flex-between" style="margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
        <div>
            <h2 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-calendar-week" style="color: var(--brand-blue, #0284c7);"></i>
                {{ __('Weekly Period Timetable') }} ({{ $class->activeSchedules->count() }})
            </h2>
            <div class="muted small" style="margin-top: 2px;">
                {{ __('Schedule subject teachers for specific periods across the week. Morning roll-call should be marked Primary.') }}
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <!-- View Mode Switcher -->
            <div style="display: inline-flex; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1;">
                <button type="button" id="btnClassViewGrid" class="btn btn-primary btn-sm" onclick="switchClassTimetableView('grid')" style="border-radius: 0; border: none; padding: 5px 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-table-cells"></i> {{ __('Grid') }}
                </button>
                <button type="button" id="btnClassViewList" class="btn btn-outline btn-sm" onclick="switchClassTimetableView('list')" style="border-radius: 0; border: none; border-left: 1px solid #cbd5e1; padding: 5px 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-list"></i> {{ __('List') }}
                </button>
            </div>

            <button type="button" class="btn btn-outline btn-sm" onclick="toggleAddScheduleForm()" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('Add Period Slot') }}</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="openClassTimetableImportModal()" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-file-csv"></i>
                <span>{{ __('Import CSV') }}</span>
            </button>
        </div>
    </div>

    <!-- Collapsible Add Period Form -->
    <div id="addScheduleBox" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
        <h3 style="margin: 0 0 12px 0; font-size: 14px; color: #0f172a; font-weight: 700;">{{ __('Add New Schedule Period') }}</h3>
        <form method="POST" action="{{ route('admin.classes.schedules.store', $class) }}">
            @csrf
            <div class="grid grid-3" style="gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">{{ __('Day of Week') }} *</label>
                    <select name="day_of_week" required style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <option value="1">{{ __('Monday') }}</option>
                        <option value="2">{{ __('Tuesday') }}</option>
                        <option value="3">{{ __('Wednesday') }}</option>
                        <option value="4">{{ __('Thursday') }}</option>
                        <option value="5">{{ __('Friday') }}</option>
                        <option value="6">{{ __('Saturday') }}</option>
                        <option value="7">{{ __('Sunday') }}</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">{{ __('Period Number') }} *</label>
                    <input type="number" name="period_number" min="1" max="15" value="1" required style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">{{ __('Subject') }} *</label>
                    <input type="text" name="subject" placeholder="{{ __('e.g. Mathematics, English...') }}" required style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
            </div>
            <div class="grid grid-3" style="gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">{{ __('Time Range') }}</label>
                    <div style="display: flex; gap: 4px; align-items: center;">
                        <input type="time" name="start_time" value="07:30" style="padding: 5px 8px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%;">
                        <span>-</span>
                        <input type="time" name="end_time" value="08:15" style="padding: 5px 8px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%;">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">{{ __('Subject Teacher') }} *</label>
                    <select name="teacher_id" required style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <option value="">{{ __('— Select Teacher —') }}</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" @selected($class->teacher_id == $t->id)>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0; display: flex; align-items: center; padding-top: 22px;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; color: #334155; font-weight: 600;">
                        <input type="checkbox" name="is_primary" value="1" checked style="width: auto;">
                        <span>{{ __('Primary Roll-Call (Rule 7 Escalation)') }}</span>
                    </label>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleAddScheduleForm()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> {{ __('Save Period') }}
                </button>
            </div>
        </form>
    </div>

    <!-- 1. Weekly Grid View (Default) -->
    <div id="timetableGridView" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 760px; margin: 0;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="width: 90px; text-align: center; color: #475569; padding: 10px 8px; border-right: 1px solid #e2e8f0;">
                        {{ __('Period') }}
                    </th>
                    @foreach($classDays as $dayIso => $dayName)
                        <th style="text-align: center; color: #1e293b; padding: 10px 8px; border-right: 1px solid #e2e8f0;">
                            {{ $dayName }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for($p = 1; $p <= $classMaxPeriod; $p++)
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="text-align: center; background: #f8fafc; border-right: 1px solid #e2e8f0; vertical-align: middle; padding: 8px 6px;">
                            <strong style="color: var(--brand-gold, #d97706); font-size: 14px;">#{{ $p }}</strong>
                        </td>
                        @foreach($classDays as $dayIso => $dayName)
                            @php
                                $sched = $classSchedulesMap->get($dayIso . '_' . $p);
                            @endphp
                            <td style="padding: 6px; vertical-align: top; border-right: 1px solid #e2e8f0; height: 95px;">
                                @if($sched)
                                    <div style="height: 100%; border-radius: 6px; padding: 8px; display: flex; flex-direction: column; justify-content: space-between; border-left: 3px solid #0284c7; background: #f0f9ff; font-size: 12px;">
                                        <div>
                                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
                                                <strong style="color: #0f172a; font-size: 12px;">{{ $sched->subject }}</strong>
                                                @if($sched->is_primary)
                                                    <span class="badge badge-violet" style="font-size: 8px; padding: 1px 3px;" title="{{ __('Primary roll-call period') }}">{{ __('Primary') }}</span>
                                                @endif
                                            </div>
                                            <div style="color: #475569; font-size: 11px; margin-bottom: 2px;">
                                                <i class="fa-solid fa-user-tie" style="font-size: 10px; color: #64748b;"></i> {{ $sched->teacher->name ?? '—' }}
                                            </div>
                                            @if($sched->start_time)
                                                <div class="muted small" style="font-size: 10px;">
                                                    <i class="fa-regular fa-clock" style="font-size: 9px;"></i> {{ $sched->timeRange() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div style="display: flex; gap: 4px; justify-content: flex-end; margin-top: 4px; padding-top: 4px; border-top: 1px dashed rgba(148, 163, 184, 0.4);">
                                            <a href="{{ route('admin.substitutions.index') }}" class="btn btn-outline btn-sm" style="padding: 1px 5px; font-size: 10px;" title="{{ __('Assign temporary substitute') }}">
                                                <i class="fa-solid fa-user-clock"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.classes.schedules.destroy', [$class, $sched]) }}" onsubmit="return confirm('{{ __('Remove this period schedule?') }}')" style="display: inline; margin: 0;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm text-red" style="padding: 1px 5px; font-size: 10px;" title="{{ __('Delete Period') }}">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <div style="height: 100%; display: flex; align-items: center; justify-content: center;">
                                        <button type="button" onclick="toggleAddScheduleForm({{ $dayIso }}, {{ $p }})" class="btn btn-outline btn-sm" style="border: 1px dashed #cbd5e1; color: #94a3b8; padding: 2px 8px; font-size: 11px;" title="{{ __('Add period for :day Period #:period', ['day' => $dayName, 'period' => $p]) }}">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <!-- 2. List View (Toggleable) -->
    <div id="timetableListView" style="display: none;">
        @if($class->activeSchedules->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Day') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Teacher') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th style="text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($class->activeSchedules as $sched)
                        <tr>
                            <td><strong>{{ $sched->dayName() }}</strong></td>
                            <td><span class="badge badge-blue">#{{ $sched->period_number }}</span></td>
                            <td><span class="small muted">{{ $sched->timeRange() ?: '—' }}</span></td>
                            <td><strong>{{ $sched->subject }}</strong></td>
                            <td>{{ $sched->teacher->name ?? '—' }}</td>
                            <td>
                                @if($sched->is_primary)
                                    <span class="badge badge-violet" style="font-size: 10px; font-weight: 700;">{{ __('Primary') }}</span>
                                @else
                                    <span class="badge badge-slate" style="font-size: 10px;">{{ __('Secondary') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.substitutions.index') }}" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 11px;" title="{{ __('Assign temporary substitute') }}">
                                    <i class="fa-solid fa-user-clock"></i> {{ __('Substitute') }}
                                </a>
                                <form method="POST" action="{{ route('admin.classes.schedules.destroy', [$class, $sched]) }}" onsubmit="return confirm('{{ __('Remove this period schedule?') }}')" style="display: inline; margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm text-red" style="padding: 2px 6px; font-size: 11px;" title="{{ __('Delete Period') }}">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 18px; text-align: center;">
                <div style="color: #64748b; font-size: 13px;">
                    <i class="fa-solid fa-calendar-plus" style="margin-right: 4px;"></i>
                    {{ __('No period schedules created yet for this class. Click "Add Period Slot" to define subject timetable periods.') }}
                </div>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <h2>{{ __('Students') }} ({{ $class->students->count() }})</h2>
    <table>
        <thead>
            <tr><th>{{ __('N.O') }}</th><th>{{ __('Student') }}</th><th>{{ __('Parent') }}</th><th></th></tr>
        </thead>
        <tbody>
            @forelse($class->students as $student)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $student->name }}</strong></td>
                    <td>{{ $student->parent_name ?? '—' }}</td>
                    <td><a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-primary">{{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">{{ __('No students in this class yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <div class="flex-between" style="margin-bottom: 12px;">
        <h2 style="margin: 0;">{{ __('Attendance Sessions') }}</h2>
        @php
            $todayHomeroomSession = $sessions->first(fn($s) => $s->session_date->isToday() && $s->class_schedule_id === null);
        @endphp
        @if(!$todayHomeroomSession && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin() || auth()->user()->id === $class->teacher_id))
            <form method="POST" action="{{ route('teacher.attendance.open', $class) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-clipboard-check"></i> {{ __('Open Today\'s Homeroom Attendance') }}
                </button>
            </form>
        @endif
    </div>
    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Period / Slot') }}</th>
                <th>{{ __('Opened') }}</th>
                <th>{{ __('Submitted') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Present') }}</th>
                <th>{{ __('Absent') }}</th>
                <th>{{ __('Permission') }}</th>
                <th>{{ __('Late') }}</th>
                <th>{{ __('Excused') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $session)
                @php
                    $stats = $session->attendances->groupBy('final_status')->map->count();
                @endphp
                <tr>
                    <td>{{ $session->session_date->format('D, d M Y') }}</td>
                    <td>
                        @if($session->schedule)
                            <span class="badge badge-blue">#{{ $session->schedule->period_number }} {{ $session->schedule->subject }}</span>
                            @if($session->schedule->is_primary)
                                <span class="badge badge-violet" style="font-size:9px; padding:1px 4px;">{{ __('Primary') }}</span>
                            @endif
                        @else
                            <span class="badge badge-slate">{{ __('Homeroom') }}</span>
                        @endif
                    </td>
                    <td>{{ $session->opened_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $session->submitted_at?->format('H:i') ?? '—' }}</td>
                    <td><x-status-badge :status="$session->status" /></td>
                    <td class="text-green">{{ $stats['present'] ?? 0 }}</td>
                    <td>{{ $stats['absent_without_permission'] ?? 0 }}</td>
                    <td>{{ $session->attendances->where('status', 'permission')->count() }}</td>
                    <td class="text-amber">{{ $stats['late'] ?? 0 }}</td>
                    <td class="text-green">{{ $stats['excused'] ?? 0 }}</td>
                    <td>
                        <a href="{{ route('teacher.attendance.mark', $session) }}" class="btn btn-sm btn-outline">
                            {{ $session->status === 'open' ? __('Mark') : __('View') }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty">{{ __('No attendance sessions yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
function toggleAddScheduleForm(day = null, period = null) {
    const box = document.getElementById('addScheduleBox');
    if (box) {
        if (day !== null && period !== null) {
            box.style.display = 'block';
            const daySelect = box.querySelector('select[name="day_of_week"]');
            const periodInput = box.querySelector('input[name="period_number"]');
            if (daySelect) daySelect.value = day;
            if (periodInput) periodInput.value = period;
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
        }
    }
}

function switchClassTimetableView(view) {
    const gridView = document.getElementById('timetableGridView');
    const listView = document.getElementById('timetableListView');
    const btnGrid = document.getElementById('btnClassViewGrid');
    const btnList = document.getElementById('btnClassViewList');

    if (view === 'grid') {
        if (gridView) gridView.style.display = 'block';
        if (listView) listView.style.display = 'none';
        if (btnGrid) {
            btnGrid.classList.add('btn-primary');
            btnGrid.classList.remove('btn-outline');
        }
        if (btnList) {
            btnList.classList.add('btn-outline');
            btnList.classList.remove('btn-primary');
        }
    } else {
        if (gridView) gridView.style.display = 'none';
        if (listView) listView.style.display = 'block';
        if (btnGrid) {
            btnGrid.classList.add('btn-outline');
            btnGrid.classList.remove('btn-primary');
        }
        if (btnList) {
            btnList.classList.add('btn-primary');
            btnList.classList.remove('btn-outline');
        }
    }
}

function openClassTimetableImportModal() {
    const m = document.getElementById('classTimetableImportModal');
    if (m) { m.style.display = 'flex'; }
}
function closeClassTimetableImportModal() {
    const m = document.getElementById('classTimetableImportModal');
    if (m) { m.style.display = 'none'; }
}
</script>

<!-- Class Timetable CSV Import Modal -->
<div id="classTimetableImportModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal-card" style="background: #ffffff; border-radius: 12px; width: 92%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); border: 1px solid #e2e8f0;">
        <div class="flex-between" style="margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-calendar-week" style="color: var(--brand-blue, #0284c7);"></i>
                {{ __('Import Timetable for') }} {{ $class->name }}
            </h3>
            <button type="button" onclick="closeClassTimetableImportModal()" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">&times;</button>
        </div>
        <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 16px;">
            {{ __('Upload a CSV file containing weekly period schedules. Existing period slots for this class will be updated or appended.') }}
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
                <label for="csv_file_class">{{ __('Select CSV File') }} <span class="required">*</span></label>
                <input type="file" id="csv_file_class" name="csv_file" accept=".csv,text/csv,text/plain" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                    <input type="checkbox" name="truncate" value="1" style="width: auto;">
                    <span>{{ __('Overwrite: Clear existing period schedules for classes in CSV') }}</span>
                </label>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeClassTimetableImportModal()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-upload"></i> {{ __('Upload & Import') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection