@extends('layouts.app')

@section('title', __('Class Attendance'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Attendance') }} — {{ $session->classRoom->name }}</div>
        <div class="page-sub">
            {{ $session->session_date->format('l, d M Y') }} ·
            {{ __('Opened at') }} {{ $session->opened_at?->format('H:i') ?? '—' }} ·
            {{ __('Status') }}: <x-status-badge :status="$session->status" />
        </div>
    </div>
    @if($session->status === 'submitted')
        <span class="badge badge-blue">{{ __('Submitted') }} {{ $session->submitted_at?->format('H:i') }}</span>
    @endif
</div>

@if($session->status === 'open')
    <div class="alert alert-info">
        <strong>{{ __('NOTE:') }}</strong> {{ __('students with an approved permission show as') }}
        <span class="badge badge-violet"><i class="fa-solid fa-lock"></i> {{ __('PERMISSION') }}</span> {{ __('and') }} <strong>{{ __('cannot be changed') }}</strong>.
        {{ __('You can only put') }} <strong>{{ __('Present') }}</strong> {{ __('or') }} <strong>{{ __('Absent') }}</strong>.
    </div>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
        <button type="button" class="btn btn-secondary btn-sm" id="markAllPresentBtn">
            <i class="fa-solid fa-check-double"></i> {{ __('Mark All Present') }}
        </button>
    </div>

    <form method="POST" action="{{ route('teacher.attendance.save', $session) }}" id="markForm">
        @csrf
        <table>
            <thead>
                <tr>
                    <th>{{ __('N.O') }}</th>
                    <th>{{ __('Student') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="width:280px">{{ __('Teacher Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($session->attendances->sortBy(fn($a) => $a->student->name) as $attendance)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;">
                                {{ $attendance->student->name }}
                                @if($attendance->student->khmer_name)
                                    <span class="font-khmer" lang="km" style="font-weight: 500; color: #475569; margin-left: 4px;">({{ $attendance->student->khmer_name }})</span>
                                @endif
                            </div>
                            <div class="small muted" style="display: flex; gap: 6px; align-items: center; margin-top: 2px;">
                                @if($attendance->student->student_code)
                                    <span style="background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 0.72rem; color: #334155; border: 1px solid #e2e8f0;">
                                        <i class="fa-solid fa-id-card text-muted"></i> {{ $attendance->student->student_code }}
                                    </span>
                                @endif
                                @if($attendance->student->gender)
                                    <span class="badge {{ $attendance->student->gender === 'F' ? 'badge-pink' : 'badge-blue' }}" style="font-size: 0.7rem; padding: 1px 6px;">
                                        {{ $attendance->student->gender === 'F' ? __('Female') : __('Male') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($attendance->is_locked)
                                <span class="badge badge-violet"><i class="fa-solid fa-lock"></i> {{ __('PERMISSION') }}</span>
                            @elseif($attendance->status)
                                <x-status-badge :status="$attendance->status" />
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($attendance->is_locked)
                                <div class="small muted">
                                    {{ $attendance->lock_reason }}
                                    @if($attendance->permission && $attendance->permission->reason)
                                        — <em>{{ $attendance->permission->reason }}</em>
                                    @endif
                                </div>
                            @else
                                <div>
                                    <label style="display:inline-flex; align-items:center; gap:4px; margin-right:12px; cursor:pointer">
                                        <input type="radio" name="statuses[{{ $attendance->student_id }}]" value="present"
                                               style="width:auto"
                                               @checked($attendance->status === 'present')>
                                        {{ __('Present') }}
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer">
                                        <input type="radio" name="statuses[{{ $attendance->student_id }}]" value="absent"
                                               style="width:auto"
                                               @checked($attendance->status === 'absent')>
                                        {{ __('Absent') }}
                                    </label>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </form>

    <div class="card flex-between">
        <div class="muted small">
            {{ __('Please verify student attendance before submitting.') }}
        </div>
        <div style="display:flex; gap:10px;">
            <button type="submit" form="markForm" class="btn btn-outline">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Draft') }}
            </button>
            <button type="button" id="openSubmitModalBtn" class="btn btn-green">
                <i class="fa-solid fa-paper-plane"></i> {{ __('Submit Attendance') }}
            </button>
        </div>
    </div>

    <!-- Interactive Submission Confirmation Modal (Requires explicit teacher confirmation, never auto-dismisses) -->
    <div id="submitConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(3px);">
        <div style="background:#fff; border-radius:14px; max-width:540px; width:92%; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:20px;">
                <div style="width:48px; height:48px; border-radius:50%; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h3 style="margin:0 0 4px 0; font-size:20px; font-weight:700; color:#0f172a;">{{ __('Confirm Attendance Submission') }}</h3>
                    <div style="font-size:13px; color:#64748b;">
                        {{ $session->classRoom->name }} · {{ $session->session_date->format('l, d M Y') }}
                    </div>
                </div>
            </div>

            <!-- Summary counts grid -->
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:20px;">
                <div style="background:var(--green-soft); border:1px solid var(--green-border); border-radius:10px; padding:12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--green); letter-spacing:0.5px;">{{ __('Present') }}</div>
                    <div id="modalCountPresent" style="font-size:24px; font-weight:800; color:var(--green); margin-top:4px;">0</div>
                </div>
                <div style="background:var(--red-soft); border:1px solid var(--red-border); border-radius:10px; padding:12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--red); letter-spacing:0.5px;">{{ __('Absent') }}</div>
                    <div id="modalCountAbsent" style="font-size:24px; font-weight:800; color:var(--red); margin-top:4px;">0</div>
                </div>
                <div style="background:var(--brand-blue-soft); border:1px solid var(--brand-blue-border); border-radius:10px; padding:12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--brand-blue); letter-spacing:0.5px;">{{ __('Permission') }}</div>
                    <div id="modalCountPermission" style="font-size:24px; font-weight:800; color:var(--brand-blue); margin-top:4px;">0</div>
                </div>
            </div>

            <div id="unmarkedWarning" style="display:none; background:var(--brand-gold-soft); border-left:4px solid var(--brand-gold); padding:12px 14px; border-radius:6px; margin-bottom:18px;">
                <div style="font-weight:700; font-size:13px; color:var(--brand-gold-hover);">
                    <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Unmarked Students Detected') }}
                </div>
                <div style="font-size:12px; color:var(--brand-gold-hover); margin-top:2px;">
                    {{ __('There are') }} <span id="unmarkedCount" style="font-weight:700;">0</span> {{ __('students not yet marked. All active students must be marked before finalizing attendance.') }}
                </div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:24px;">
                <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                    <i class="fa-solid fa-lock" style="color:#64748b; margin-right:4px;"></i>
                    {{ __('Submitting attendance will') }} <strong>{{ __("lock today's roll-call records") }}</strong>{{ __('. Present students will be finalized, and any Absent students will be forwarded to Student Affairs for arrival verification.') }}
                </p>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn btn-outline" onclick="closeSubmitModal()">
                    {{ __('Review / Keep Editing') }}
                </button>
                <button type="button" id="confirmSubmitBtn" class="btn btn-green">
                    <i class="fa-solid fa-check-double"></i> {{ __('Confirm & Submit Attendance') }}
                </button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('teacher.attendance.submit', $session) }}" id="submitForm">
        @csrf
    </form>
@else
    @include('teacher.attendance._submitted_table', ['session' => $session])
@endif

<script>
    document.getElementById('markAllPresentBtn')?.addEventListener('click', function() {
        document.querySelectorAll('input[type="radio"][value="present"]').forEach(function(radio) {
            radio.checked = true;
        });
    });

    const openSubmitModalBtn = document.getElementById('openSubmitModalBtn');
    const submitModal = document.getElementById('submitConfirmModal');
    const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');

    openSubmitModalBtn?.addEventListener('click', function() {
        let presentCount = 0;
        let absentCount = 0;
        let permissionCount = {{ $session->attendances->where('is_locked', true)->count() }};
        let unmarkedCount = 0;

        const radioGroups = {};
        document.querySelectorAll('#markForm input[type="radio"]').forEach(function(radio) {
            const name = radio.name;
            if (!radioGroups[name]) {
                radioGroups[name] = false;
            }
            if (radio.checked) {
                radioGroups[name] = true;
                if (radio.value === 'present') presentCount++;
                if (radio.value === 'absent') absentCount++;
            }
        });

        for (const key in radioGroups) {
            if (!radioGroups[key]) {
                unmarkedCount++;
            }
        }

        document.getElementById('modalCountPresent').textContent = presentCount;
        document.getElementById('modalCountAbsent').textContent = absentCount;
        document.getElementById('modalCountPermission').textContent = permissionCount;

        const warningEl = document.getElementById('unmarkedWarning');
        const unmarkedCountEl = document.getElementById('unmarkedCount');

        if (unmarkedCount > 0) {
            unmarkedCountEl.textContent = unmarkedCount;
            warningEl.style.display = 'block';
            confirmSubmitBtn.disabled = true;
            confirmSubmitBtn.style.opacity = '0.5';
            confirmSubmitBtn.style.cursor = 'not-allowed';
        } else {
            warningEl.style.display = 'none';
            confirmSubmitBtn.disabled = false;
            confirmSubmitBtn.style.opacity = '1';
            confirmSubmitBtn.style.cursor = 'pointer';
        }

        submitModal.style.display = 'flex';
    });

    function closeSubmitModal() {
        if (submitModal) {
            submitModal.style.display = 'none';
        }
    }

    confirmSubmitBtn?.addEventListener('click', function() {
        const markForm = document.getElementById('markForm');
        if (!markForm) {
            document.getElementById('submitForm').submit();
            return;
        }

        const markFormData = new FormData(markForm);
        fetch("{{ route('teacher.attendance.save', $session) }}", {
            method: 'POST',
            body: markFormData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function() {
            document.getElementById('submitForm').submit();
        }).catch(function() {
            document.getElementById('submitForm').submit();
        });
    });
</script>
@endsection