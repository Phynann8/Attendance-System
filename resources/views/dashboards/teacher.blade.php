@extends('layouts.app')

@section('title', __('Teacher Dashboard'))

@section('content')
<div class="page-title">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</div>
<div class="page-sub">{{ __('Please check student attendance on time. Permission students are locked.') }}</div>

@if(isset($todayPeriods) && $todayPeriods->isNotEmpty())
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--brand-blue, #0284c7);">
        <div class="flex-between" style="margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
            <div>
                <h2 style="margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-day" style="color: var(--brand-blue, #0284c7);"></i>
                    {{ __("Today's Teaching Schedule") }} — {{ now()->format('l, d M Y') }}
                </h2>
                <div class="muted small" style="margin-top: 2px;">
                    {{ __(':count period(s) scheduled for today', ['count' => $todayPeriods->count()]) }}
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <a href="{{ route('teacher.schedule.index') }}" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-calendar-week"></i>
                    {{ __('Full Weekly Timetable') }}
                </a>
                <span class="badge badge-blue">{{ now()->format('l') }}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 90px;">{{ __('Period') }}</th>
                    <th style="width: 120px;">{{ __('Time') }}</th>
                    <th>{{ __('Class') }}</th>
                    <th>{{ __('Subject') }}</th>
                    <th>{{ __('Status / Role') }}</th>
                    <th style="text-align: right; width: 190px;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPeriods as $p)
                    <tr>
                        <td>
                            <strong style="color: var(--brand-gold);">Period #{{ $p->period_number }}</strong>
                            @if($p->is_primary)
                                <span class="badge badge-violet" style="font-size: 9px; padding: 1px 4px; margin-left: 2px;" title="{{ __('Primary morning roll-call') }}">
                                    {{ __('Primary') }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="muted" style="font-size: 13px; font-weight: 500;">
                                <i class="fa-regular fa-clock" style="font-size: 11px;"></i> {{ $p->schedule->timeRange() ?: '—' }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $p->class?->name ?? '—' }}</strong>
                            @if($p->class?->campus)
                                <span class="badge badge-slate" style="font-size: 10px; padding: 1px 5px; font-weight: 700; margin-left: 4px;">{{ $p->class->campus->code }}</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-weight: 600; color: #1e293b;">{{ $p->subject }}</span>
                        </td>
                        <td>
                            @if($p->is_substitute)
                                <span class="badge badge-amber" style="font-size: 11px;">
                                    <i class="fa-solid fa-user-clock"></i> {{ __('Covering for :name', ['name' => $p->original_teacher?->name ?? __('Teacher')]) }}
                                </span>
                            @elseif($p->is_covered)
                                <span class="badge badge-slate" style="font-size: 11px;">
                                    <i class="fa-solid fa-umbrella"></i> {{ __('Covered by :name', ['name' => $p->substitute_teacher?->name ?? __('Substitute')]) }}
                                </span>
                            @elseif($p->session)
                                <x-status-badge :status="$p->session->status" />
                            @else
                                <span class="badge badge-slate">{{ __('Not Started') }}</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            @if($p->session)
                                @if($p->session->status === 'open')
                                    <a href="{{ route('teacher.attendance.mark', $p->session) }}" class="btn btn-amber btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-pen-to-square"></i> {{ __('Mark') }}
                                    </a>
                                @elseif($p->session->status === 'submitted')
                                    <a href="{{ route('teacher.attendance.mark', $p->session) }}" class="btn btn-green btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-eye"></i> {{ __('View') }}
                                    </a>
                                @else
                                    <span class="badge badge-slate">{{ __('Closed') }}</span>
                                @endif
                            @elseif($p->is_covered)
                                <span class="muted small">—</span>
                            @else
                                <form method="POST" action="{{ route('teacher.attendance.open', $p->class) }}" style="display: inline; margin: 0;">
                                    @csrf
                                    <input type="hidden" name="schedule_id" value="{{ $p->schedule->id }}">
                                    <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-clipboard-check"></i> {{ __('Open') }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($classes->isNotEmpty())
    <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-chalkboard-user" style="color: var(--brand-gold);"></i>
            {{ __('Homeroom Classes') }}
        </h3>
    </div>

    @foreach($classes as $class)
        <div class="card" style="margin-bottom: 16px;">
            <div class="flex-between">
                <div>
                    <h2>{{ $class->name }} <span class="muted small">{{ $class->grade }}</span></h2>
                    <div class="small muted">{{ __(':count active student(s)', ['count' => $class->studentCount]) }}</div>
                </div>

                @if($class->todaySession)
                    @if($class->todaySession->status === 'open')
                        <a href="{{ route('teacher.attendance.mark', $class->todaySession) }}" class="btn btn-amber">{{ __("Check today's attendance") }}</a>
                    @elseif($class->todaySession->status === 'submitted')
                        <a href="{{ route('teacher.attendance.mark', $class->todaySession) }}" class="btn btn-green">{{ __('View submitted attendance') }}</a>
                    @else
                        <span class="badge badge-slate">{{ __('Closed') }}</span>
                    @endif
                @else
                    <form method="POST" action="{{ route('teacher.attendance.open', $class) }}">
                        @csrf
                        <button type="submit" class="btn">{{ __('Open Homeroom Attendance for :date', ['date' => now()->format('d M')]) }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
@endif

@if((!isset($todayPeriods) || $todayPeriods->isEmpty()) && $classes->isEmpty())
    <div class="card empty">{{ __('No classes or teaching periods assigned to you yet.') }}</div>
@endif
@endsection