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
    <a href="{{ auth()->user()->isTeacher() ? route('teacher.attendance.history') : route('admin.reports.index') }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-arrow-left"></i> {{ auth()->user()->isTeacher() ? __('Back to history') : __('Back to reports') }}
    </a>

    @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
        <button type="button" class="btn btn-gold btn-sm" onclick="openReopenModal()">
            <i class="fa-solid fa-arrow-rotate-left"></i> {{ __('Reopen Session for Amendment') }}
        </button>
    @endif
</div>

@if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
<div id="reopenModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
    <div style="background:#fff; border-radius:12px; max-width:480px; width:90%; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <div style="width:40px; height:40px; border-radius:50%; background:var(--brand-gold-soft); color:var(--brand-gold); display:flex; align-items:center; justify-content:center; font-size:18px;">
                <i class="fa-solid fa-arrow-rotate-left"></i>
            </div>
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">{{ __('Reopen Attendance Session') }}</h3>
                <div style="font-size:12px; color:#64748b;">{{ __('Session') }} #{{ $session->id }} — {{ $session->classRoom->name }} ({{ $session->session_date->format('d M Y') }})</div>
            </div>
        </div>

        <p style="font-size:13px; color:#475569; line-height:1.5; margin-bottom:16px;">
            {{ __('Reopening this session will reset status to') }} <strong>{{ __('Open') }}</strong> {{ __('and allow teacher or administration to amend roll-call records. Locked parent permissions will remain strictly preserved.') }}
        </p>

        <form method="POST" action="{{ route('admin.attendance-sessions.reopen', $session) }}">
            @csrf
            <div class="form-group" style="margin-bottom:16px;">
                <label for="reopen_reason" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                    {{ __('Mandatory Audit Reason') }} <span class="required">*</span>
                </label>
                <textarea id="reopen_reason" name="reason" rows="3" required placeholder="{{ __('e.g. Teacher misrecorded 2 students, correcting with verified note...') }}" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font-size:13px; box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeReopenModal()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-gold btn-sm">
                    <i class="fa-solid fa-check"></i> {{ __('Confirm Reopen') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openReopenModal() {
    document.getElementById('reopenModal').style.display = 'flex';
}
function closeReopenModal() {
    document.getElementById('reopenModal').style.display = 'none';
}
</script>
@endif