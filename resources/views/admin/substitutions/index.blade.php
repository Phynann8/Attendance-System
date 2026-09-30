@extends('layouts.app')

@section('title', __('Teacher Substitutions'))

@section('content')
<div class="flex-between" style="align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <div class="page-title">{{ __('Teacher Substitutions') }}</div>
        <div class="page-sub">{{ __('Assign and manage temporary period coverages for absent or sick teachers.') }}</div>
    </div>
    <button type="button" class="btn btn-primary" onclick="openSubstitutionModal()" style="display: inline-flex; align-items: center; gap: 6px;">
        <i class="fa-solid fa-user-clock"></i>
        <span>{{ __('+ Assign Substitute Teacher') }}</span>
    </button>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Period & Subject') }}</th>
                <th>{{ __('Original Teacher') }}</th>
                <th>{{ __('Substitute Teacher') }}</th>
                <th>{{ __('Reason') }}</th>
                <th>{{ __('Assigned By') }}</th>
                <th style="text-align: right;">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($substitutions as $sub)
                <tr>
                    <td>
                        <strong>{{ $sub->session_date->format('D, d M Y') }}</strong>
                        @if($sub->session_date->isToday())
                            <span class="badge badge-green" style="font-size: 10px; margin-left: 4px;">{{ __('Today') }}</span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $sub->classSchedule->classRoom->name ?? '—' }}</strong>
                        @if($sub->classSchedule?->classRoom?->campus)
                            <span class="badge badge-slate" style="font-size: 10px; font-weight: 700; margin-left: 2px;">
                                {{ $sub->classSchedule->classRoom->campus->code }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #1e293b;">
                            #{{ $sub->classSchedule->period_number }} — {{ $sub->classSchedule->subject }}
                        </div>
                        <div class="small muted">
                            {{ $sub->classSchedule->timeRange() ?: $sub->classSchedule->dayName() }}
                        </div>
                    </td>
                    <td>
                        <span class="muted">{{ $sub->originalTeacher->name ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-amber" style="font-size: 12px; font-weight: 600; padding: 4px 8px;">
                            <i class="fa-solid fa-user-check"></i> {{ $sub->substituteTeacher->name ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <span class="small">{{ $sub->reason ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="small muted">{{ $sub->creator->name ?? '—' }}</span>
                    </td>
                    <td style="text-align: right;">
                        <form method="POST" action="{{ route('admin.substitutions.destroy', $sub) }}" onsubmit="return confirm('{{ __('Cancel this substitution coverage?') }}')" style="display: inline; margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm text-red" style="padding: 4px 8px;" title="{{ __('Cancel Substitution') }}">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">
                        {{ __('No teacher substitutions recorded yet.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($substitutions->hasPages())
        <div style="margin-top: 16px;">
            {{ $substitutions->links() }}
        </div>
    @endif
</div>

<!-- Modal: Assign Substitute Teacher -->
<div id="substitutionModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: #fff; border-radius: 14px; max-width: 560px; width: 92%; padding: 26px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">{{ __('Assign Substitute Teacher') }}</h3>
                    <div style="font-size: 12px; color: #64748b;">{{ __('Cover a scheduled class period for an absent teacher.') }}</div>
                </div>
            </div>
            <button type="button" onclick="closeSubstitutionModal()" style="background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.substitutions.store') }}">
            @csrf

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="modal_class_schedule_id" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                    {{ __('Class Period Schedule Slot') }} <span class="required">*</span>
                </label>
                <select id="modal_class_schedule_id" name="class_schedule_id" required style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px;">
                    <option value="">{{ __('— Select Class & Period Slot —') }}</option>
                    @foreach($schedules as $sched)
                        <option value="{{ $sched->id }}">
                            {{ $sched->classRoom?->name }} ({{ $sched->classRoom?->campus?->code }}) — {{ $sched->dayName() }} #{{ $sched->period_number }}: {{ $sched->subject }} [{{ $sched->teacher->name }}]
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-2" style="margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="modal_session_date" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        {{ __('Date of Substitution') }} <span class="required">*</span>
                    </label>
                    <input type="date" id="modal_session_date" name="session_date" value="{{ today()->format('Y-m-d') }}" required style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="modal_substitute_teacher_id" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        {{ __('Substitute Teacher (Covering)') }} <span class="required">*</span>
                    </label>
                    <select id="modal_substitute_teacher_id" name="substitute_teacher_id" required style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px;">
                        <option value="">{{ __('— Select Active Teacher —') }}</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}">
                                {{ $teacher->name }} ({{ $teacher->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="modal_reason" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                    {{ __('Reason / Note') }} ({{ __('Optional') }})
                </label>
                <input type="text" id="modal_reason" name="reason" placeholder="{{ __('e.g. Medical clinic leave, attending training workshop...') }}" style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeSubstitutionModal()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-check"></i> {{ __('Save Substitution') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSubstitutionModal() {
    document.getElementById('substitutionModal').style.display = 'flex';
}
function closeSubstitutionModal() {
    document.getElementById('substitutionModal').style.display = 'none';
}
window.addEventListener('click', function(e) {
    const modal = document.getElementById('substitutionModal');
    if (modal && e.target === modal) closeSubstitutionModal();
});
</script>
@endsection
