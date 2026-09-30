@extends('layouts.app')

@section('title', __('Weekly Timetable') . ' — ' . $targetTeacher->name)

@section('content')
<div class="flex-between" style="flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
    <div>
        <div class="page-title" style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-calendar-week" style="color: var(--brand-blue, #0284c7);"></i>
            {{ __('Weekly Teaching Timetable') }}
            @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                <span class="badge badge-blue" style="font-size: 13px; font-weight: 600;">{{ $targetTeacher->name }}</span>
            @endif
        </div>
        <div class="page-sub">
            {{ $weekStart->format('d M Y') }} – {{ $weekEnd->format('d M Y') }}
            <span class="muted">•</span>
            <span style="color: #0284c7; font-weight: 600;">{{ __(':count teaching period(s) / week', ['count' => $weeklyPeriodsCount]) }}</span>
        </div>
    </div>

    <!-- Navigation Controls & Filters -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
            <form method="GET" action="{{ route('teacher.schedule.index') }}" style="display: flex; align-items: center; gap: 6px; margin: 0;">
                <input type="hidden" name="date" value="{{ $weekStart->format('Y-m-d') }}">
                <label for="teacher_id" class="small muted" style="font-weight: 600;">{{ __('Teacher:') }}</label>
                <select name="teacher_id" id="teacher_id" onchange="this.form.submit()" style="padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;">
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected($targetTeacher->id === $t->id)>
                            {{ $t->name }} {{ $t->campus ? '(' . $t->campus->code . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        <div style="display: inline-flex; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1;">
            <a href="{{ route('teacher.schedule.index', ['date' => $prevWeek, 'teacher_id' => $targetTeacher->id]) }}" class="btn btn-outline btn-sm" style="border: none; border-radius: 0; padding: 7px 12px;" title="{{ __('Previous Week') }}">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a href="{{ route('teacher.schedule.index', ['date' => $todayDate, 'teacher_id' => $targetTeacher->id]) }}" class="btn btn-outline btn-sm" style="border: none; border-radius: 0; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 7px 12px; font-weight: 600;">
                {{ __('This Week') }}
            </a>
            <a href="{{ route('teacher.schedule.index', ['date' => $nextWeek, 'teacher_id' => $targetTeacher->id]) }}" class="btn btn-outline btn-sm" style="border: none; border-radius: 0; padding: 7px 12px;" title="{{ __('Next Week') }}">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- Weekly Grid Card -->
<div class="card" style="padding: 0; overflow-x: auto; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06);">
    <table style="width: 100%; border-collapse: collapse; min-width: 900px; margin: 0;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <th style="width: 110px; padding: 14px 12px; text-align: center; color: #475569; font-weight: 700; border-right: 1px solid #e2e8f0;">
                    <i class="fa-regular fa-clock" style="margin-right: 4px;"></i> {{ __('Period') }}
                </th>
                @foreach($days as $dayIso => $day)
                    <th style="padding: 12px 10px; text-align: center; border-right: 1px solid #e2e8f0; {{ $day['is_today'] ? 'background: #eff6ff;' : '' }}">
                        <div style="font-size: 14px; font-weight: 700; color: {{ $day['is_today'] ? '#1d4ed8' : '#1e293b' }};">
                            {{ $day['name'] }}
                        </div>
                        <div style="font-size: 11px; font-weight: 600; color: {{ $day['is_today'] ? '#2563eb' : '#64748b' }}; margin-top: 2px;">
                            {{ $day['formatted'] }}
                            @if($day['is_today'])
                                <span class="badge badge-blue" style="font-size: 9px; padding: 1px 5px; margin-left: 3px;">{{ __('Today') }}</span>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for($period = 1; $period <= $maxPeriod; $period++)
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <!-- Period Number & Time -->
                    <td style="padding: 14px 10px; text-align: center; background: #f8fafc; border-right: 1px solid #e2e8f0; vertical-align: middle;">
                        <strong style="color: var(--brand-gold, #d97706); font-size: 15px;">#{{ $period }}</strong>
                        @if(isset($periodTimes[$period]))
                            <div class="small muted" style="font-size: 11px; margin-top: 3px; font-weight: 500;">
                                {{ $periodTimes[$period] }}
                            </div>
                        @endif
                    </td>

                    <!-- Days of Week Cells -->
                    @foreach($days as $dayIso => $day)
                        @php
                            $slot = $grid[$period][$dayIso] ?? null;
                        @endphp
                        <td style="padding: 8px; vertical-align: top; border-right: 1px solid #e2e8f0; height: 105px; {{ $day['is_today'] ? 'background: rgba(239, 246, 255, 0.4);' : '' }}">
                            @if($slot)
                                <div style="height: 100%; border-radius: 8px; padding: 10px; display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid {{ $slot->is_substitute ? '#f59e0b' : ($slot->is_covered ? '#94a3b8' : '#0284c7') }}; background: {{ $slot->is_substitute ? '#fffbeb' : ($slot->is_covered ? '#f8fafc' : '#f0f9ff') }}; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                                    <div>
                                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 4px; margin-bottom: 4px;">
                                            <strong style="font-size: 13px; color: #0f172a; line-height: 1.3;">{{ $slot->subject }}</strong>
                                            @if($slot->is_primary)
                                                <span class="badge badge-violet" style="font-size: 9px; padding: 1px 4px;" title="{{ __('Primary roll-call period') }}">{{ __('Primary') }}</span>
                                            @endif
                                        </div>

                                        <div style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid fa-chalkboard-user" style="font-size: 10px; color: #64748b;"></i>
                                            {{ $slot->class?->name ?? '—' }}
                                            @if($slot->class?->campus)
                                                <span class="badge badge-slate" style="font-size: 9px; padding: 1px 4px;">{{ $slot->class->campus->code }}</span>
                                            @endif
                                        </div>

                                        @if($slot->start_time)
                                            <div class="muted small" style="font-size: 11px; margin-bottom: 6px;">
                                                <i class="fa-regular fa-clock" style="font-size: 10px;"></i> {{ $slot->schedule->timeRange() }}
                                            </div>
                                        @endif

                                        @if($slot->is_substitute)
                                            <span class="badge badge-amber" style="font-size: 10px; display: inline-flex; align-items: center; gap: 3px; margin-bottom: 4px;">
                                                <i class="fa-solid fa-user-clock"></i> {{ __('Covering for :name', ['name' => $slot->original_teacher?->name ?? __('Teacher')]) }}
                                            </span>
                                        @elseif($slot->is_covered)
                                            <span class="badge badge-slate" style="font-size: 10px; display: inline-flex; align-items: center; gap: 3px; margin-bottom: 4px;">
                                                <i class="fa-solid fa-umbrella"></i> {{ __('Covered by :name', ['name' => $slot->substitute_teacher?->name ?? __('Substitute')]) }}
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Attendance Action / Status -->
                                    <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(148, 163, 184, 0.4); display: flex; align-items: center; justify-content: space-between;">
                                        @if($slot->session)
                                            @if($slot->session->status === 'open')
                                                <a href="{{ route('teacher.attendance.mark', $slot->session) }}" class="btn btn-amber btn-sm" style="font-size: 10px; padding: 2px 7px; width: 100%; text-align: center;">
                                                    <i class="fa-solid fa-pen-to-square"></i> {{ __('Mark Open') }}
                                                </a>
                                            @elseif($slot->session->status === 'submitted')
                                                <a href="{{ route('teacher.attendance.mark', $slot->session) }}" class="btn btn-green btn-sm" style="font-size: 10px; padding: 2px 7px; width: 100%; text-align: center;">
                                                    <i class="fa-solid fa-check"></i> {{ __('Submitted') }}
                                                </a>
                                            @else
                                                <span class="badge badge-slate" style="font-size: 9px; width: 100%; text-align: center;">{{ __('Closed') }}</span>
                                            @endif
                                        @elseif($day['is_today'] && !$slot->is_covered && (auth()->user()->id === $targetTeacher->id || auth()->user()->isAdmin() || auth()->user()->isSuperAdmin()))
                                            <form method="POST" action="{{ route('teacher.attendance.open', $slot->class) }}" style="margin: 0; width: 100%;">
                                                @csrf
                                                <input type="hidden" name="schedule_id" value="{{ $slot->schedule->id }}">
                                                <button type="submit" class="btn btn-primary btn-sm" style="font-size: 10px; padding: 2px 7px; width: 100%;">
                                                    <i class="fa-solid fa-clipboard-check"></i> {{ __('Open Today') }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="muted small" style="font-size: 10px;">{{ __('Not Started') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 16px;">
                                    —
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
</div>

<!-- Legend Card -->
<div class="card" style="padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; font-size: 12px; color: #475569;">
    <strong style="color: #1e293b;"><i class="fa-solid fa-circle-info" style="margin-right: 4px; color: #0284c7;"></i> {{ __('Legend:') }}</strong>
    <span style="display: flex; align-items: center; gap: 6px;">
        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #f0f9ff; border-left: 3px solid #0284c7;"></span>
        {{ __('Regular Teaching Period') }}
    </span>
    <span style="display: flex; align-items: center; gap: 6px;">
        <span class="badge badge-violet" style="font-size: 9px; padding: 1px 4px;">{{ __('Primary') }}</span>
        {{ __('Morning Arrival Roll-Call (Alerts Student Affairs if Absent)') }}
    </span>
    <span style="display: flex; align-items: center; gap: 6px;">
        <span class="badge badge-amber" style="font-size: 9px; padding: 1px 4px;"><i class="fa-solid fa-user-clock"></i> {{ __('Covering') }}</span>
        {{ __('Temporary Substitute Coverage') }}
    </span>
    <span style="display: flex; align-items: center; gap: 6px;">
        <span class="badge badge-slate" style="font-size: 9px; padding: 1px 4px;"><i class="fa-solid fa-umbrella"></i> {{ __('Covered') }}</span>
        {{ __('Period Covered by Colleague') }}
    </span>
</div>
@endsection
