<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Student</th>
            <th>Status</th>
            <th>Arrival</th>
            <th>Minutes Late</th>
            <th>Final Result</th>
        </tr>
    </thead>
    <tbody>
        @foreach($session->attendances->sortBy(fn($a) => $a->student->name) as $attendance)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $attendance->student->name }}</strong></td>
                <td>
                    @if($attendance->status)
                        <x-status-badge :status="$attendance->status" />
                        @if($attendance->is_locked) <span class="lock-icon">🔒</span> @endif
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
                        <span class="badge">Pending</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
<p class="mt-3">
    <a href="{{ route('teacher.attendance.history') }}" class="btn btn-outline btn-sm">← Back to history</a>
</p>