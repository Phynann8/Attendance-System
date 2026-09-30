@if($session->isReopenPending())
    @if(auth()->user()->canApproveSessionReopen())
        <div class="card" style="border: 2px solid #f59e0b; background: #fffbeb; margin-bottom: 20px; border-radius: 12px; padding: 18px;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div style="display:flex; gap:14px; align-items:flex-start; flex:1; min-width:280px;">
                    <div style="width:42px; height:42px; border-radius:50%; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:700; font-size:15px; color:#92400e;">
                            {{ __('Pending Reopen Request from Teacher') }}
                        </div>
                        <div style="font-size:13px; color:#78350f; margin-top:2px;">
                            {{ __('Teacher') }} <strong>{{ $session->reopenRequester?->name ?? $session->teacher?->name ?? __('Assigned Teacher') }}</strong>
                            {{ __('requested to reopen this session on') }} {{ $session->reopen_requested_at?->format('d M Y, H:i') }}.
                        </div>
                        @if($session->reopen_reason)
                            <div style="margin-top:8px; padding:10px 14px; background:#fff; border-radius:8px; border:1px solid #fde68a; font-size:13px; color:#1e293b;">
                                <strong style="color:#b45309;">{{ __('Reason for Amendment:') }}</strong> {{ $session->reopen_reason }}
                            </div>
                        @endif
                    </div>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal()">
                        <i class="fa-solid fa-xmark"></i> {{ __('Reject') }}
                    </button>
                    <form method="POST" action="{{ route('attendance-sessions.reopen-approve', $session) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-green btn-sm" onclick="return confirm('{{ __('Are you sure you want to confirm and allow this session to be reopened for amendment?') }}')">
                            <i class="fa-solid fa-check"></i> {{ __('Confirm & Allow Reopen') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning" style="display:flex; align-items:flex-start; gap:12px; margin-bottom:16px;">
            <div style="font-size:20px; color:#d97706; margin-top:2px;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700; font-size:14px; color:#92400e;">
                    {{ __('Reopen Request Pending Confirmation') }}
                </div>
                <div style="font-size:13px; color:#b45309; margin-top:2px;">
                    {{ __('You requested to reopen this session for amendment on') }} {{ $session->reopen_requested_at?->format('d M Y, H:i') }}.
                    <strong>{{ __('Waiting for confirmation from Student Affairs, Admin, or Super Admin.') }}</strong>
                </div>
                @if($session->reopen_reason)
                    <div style="font-size:12px; color:#78350f; margin-top:6px; background:rgba(255,255,255,0.7); padding:6px 10px; border-radius:6px; border:1px solid #fde68a;">
                        <strong>{{ __('Your submitted reason:') }}</strong> {{ $session->reopen_reason }}
                    </div>
                @endif
            </div>
        </div>
    @endif
@elseif($session->reopen_status === \App\Models\AttendanceSession::REOPEN_REJECTED)
    <div class="alert alert-danger" style="margin-bottom:16px; display:flex; align-items:flex-start; gap:10px;">
        <i class="fa-solid fa-circle-xmark" style="font-size:18px; margin-top:2px;"></i>
        <div>
            <div>
                {{ __('Previous reopen request was rejected by') }}
                <strong>{{ $session->reopenDecider?->name ?? __('Administration') }}</strong>
                @if($session->reopen_decided_at)
                    ({{ $session->reopen_decided_at->format('d M Y, H:i') }})
                @endif
            </div>
            @if($session->reopen_decision_note)
                <div style="margin-top:4px; font-style:italic;">
                    "{{ $session->reopen_decision_note }}"
                </div>
            @endif
        </div>
    </div>
@endif

<table>
    <thead>
        <tr>
            <th>{{ __('N.O') }}</th>
            <th>{{ __('Student') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Arrival') }}</th>
            <th>{{ __('Minutes Late') }}</th>
            <th>{{ __('Final Result') }}</th>
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
                                {{ $attendance->student->gender === 'F' ? 'Female' : 'Male' }}
                            </span>
                        @endif
                    </div>
                </td>
                <td>
                    @if($attendance->status)
                        <x-status-badge :status="$attendance->status" />
                        @if($attendance->is_locked) <span class="lock-icon" title="Locked by Approved Permission"><i class="fa-solid fa-lock"></i></span> @endif
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td>{{ $attendance->arrived_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $attendance->minutes_late ?? '—' }}</td>
                <td>
                    @if($attendance->final_status)
                        <x-status-badge :status="$attendance->final_status" />
                    @else
                        <span class="badge">{{ __('Pending') }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="mt-3 flex-between">
    <a href="{{ auth()->user()->isTeacher() ? route('teacher.attendance.history') : (auth()->user()->isStudentAffairs() ? route('student-affairs.review.index') : route('admin.reports.index')) }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-arrow-left"></i> {{ auth()->user()->isTeacher() ? __('Back to history') : (auth()->user()->isStudentAffairs() ? __('Back to review') : __('Back to reports')) }}
    </a>

    @if(auth()->user()->canApproveSessionReopen() || $session->teacher_id === auth()->id())
        @if($session->isReopenPending())
            @if(auth()->user()->canApproveSessionReopen())
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal()">
                        <i class="fa-solid fa-xmark"></i> {{ __('Reject Request') }}
                    </button>
                    <form method="POST" action="{{ route('attendance-sessions.reopen-approve', $session) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-green btn-sm" onclick="return confirm('{{ __('Are you sure you want to confirm and allow this session to be reopened for amendment?') }}')">
                            <i class="fa-solid fa-check"></i> {{ __('Confirm & Allow Reopen') }}
                        </button>
                    </form>
                </div>
            @else
                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity:0.75; cursor:not-allowed;" title="{{ __('Waiting for confirmation from Student Affairs, Admin, or Super Admin') }}">
                    <i class="fa-solid fa-clock"></i> {{ __('Waiting for Confirmation...') }}
                </button>
            @endif
        @else
            <button type="button" class="btn btn-gold btn-sm" onclick="openReopenModal()">
                <i class="fa-solid fa-arrow-rotate-left"></i> {{ __('Reopen Session for Amendment') }}
            </button>
        @endif
    @endif
</div>

@if(auth()->user()->canApproveSessionReopen() || $session->teacher_id === auth()->id())
<div id="reopenModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
    <div style="background:#fff; border-radius:12px; max-width:480px; width:90%; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <div style="width:40px; height:40px; border-radius:50%; background:var(--brand-gold-soft); color:var(--brand-gold); display:flex; align-items:center; justify-content:center; font-size:18px;">
                <i class="fa-solid fa-arrow-rotate-left"></i>
            </div>
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">
                    {{ auth()->user()->canApproveSessionReopen() ? __('Reopen Attendance Session') : __('Request Reopen for Amendment') }}
                </h3>
                <div style="font-size:12px; color:#64748b;">{{ __('Session') }} #{{ $session->id }} — {{ $session->classRoom->name }} ({{ $session->session_date->format('d M Y') }})</div>
            </div>
        </div>

        <p style="font-size:13px; color:#475569; line-height:1.5; margin-bottom:16px;">
            @if(auth()->user()->canApproveSessionReopen())
                {{ __('Reopening this session will reset status to') }} <strong>{{ __('Open') }}</strong> {{ __('and allow teacher or administration to amend roll-call records. Locked parent permissions will remain strictly preserved.') }}
            @else
                {{ __('To amend submitted attendance records, submit a request with a clear reason.') }}
                <strong>{{ __('Student Affairs, Admin, or Super Admin must confirm and allow') }}</strong>
                {{ __('the request before the session is reopened for editing.') }}
            @endif
        </p>

        <form method="POST" action="{{ route('attendance-sessions.reopen', $session) }}">
            @csrf
            <div class="form-group" style="margin-bottom:16px;">
                <label for="reopen_reason" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                    {{ __('Mandatory Audit Reason') }} <span class="required">*</span>
                </label>
                <textarea id="reopen_reason" name="reason" rows="3" required placeholder="{{ auth()->user()->canApproveSessionReopen() ? __('e.g. Teacher misrecorded 2 students, authorized by Administration...') : __('e.g. Misrecorded 2 students, correcting with verified parent note...') }}" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font-size:13px; box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeReopenModal()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-gold btn-sm">
                    @if(auth()->user()->canApproveSessionReopen())
                        <i class="fa-solid fa-check"></i> {{ __('Confirm & Allow Reopen') }}
                    @else
                        <i class="fa-solid fa-paper-plane"></i> {{ __('Submit Request for Confirmation') }}
                    @endif
                </button>
            </div>
        </form>
    </div>
</div>

@if(auth()->user()->canApproveSessionReopen())
<div id="rejectModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
    <div style="background:#fff; border-radius:12px; max-width:440px; width:90%; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <div style="width:40px; height:40px; border-radius:50%; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:18px;">
                <i class="fa-solid fa-xmark"></i>
            </div>
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">{{ __('Reject Reopen Request') }}</h3>
                <div style="font-size:12px; color:#64748b;">{{ __('Session') }} #{{ $session->id }} — {{ $session->classRoom->name }}</div>
            </div>
        </div>

        <p style="font-size:13px; color:#475569; line-height:1.5; margin-bottom:16px;">
            {{ __('Are you sure you want to reject this request to reopen attendance? The session will remain submitted and locked.') }}
        </p>

        <form method="POST" action="{{ route('attendance-sessions.reopen-reject', $session) }}">
            @csrf
            <div class="form-group" style="margin-bottom:16px;">
                <label for="reject_note" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                    {{ __('Decision Note / Reason for Rejection') }} ({{ __('Optional') }})
                </label>
                <textarea id="reject_note" name="note" rows="3" placeholder="{{ __('e.g., Records already verified, correction not authorized.') }}" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font-size:13px; box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeRejectModal()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-ban"></i> {{ __('Reject Request') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function openReopenModal() {
    const modal = document.getElementById('reopenModal');
    if (modal) modal.style.display = 'flex';
}
function closeReopenModal() {
    const modal = document.getElementById('reopenModal');
    if (modal) modal.style.display = 'none';
}
function openRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) modal.style.display = 'flex';
}
function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) modal.style.display = 'none';
}

window.addEventListener('click', function(e) {
    const reopenModal = document.getElementById('reopenModal');
    if (reopenModal && e.target === reopenModal) closeReopenModal();
    const rejectModal = document.getElementById('rejectModal');
    if (rejectModal && e.target === rejectModal) closeRejectModal();
});
</script>
@endif