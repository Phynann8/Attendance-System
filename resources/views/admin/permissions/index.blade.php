@extends('layouts.app')

@section('title', 'Permission Requests')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Review Permission Requests</div>
        <div class="page-sub">Please Review student permissions requests before check student absent.</div>
    </div>
    <a href="{{ route('admin.permissions.create') }}" class="btn">+ Assign Permission</a>
</div>

<div class="card">
    <form method="GET" id="filterForm" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
            </select>
        </div>
        <div class="form-group">
            <label for="class_id">Class</label>
            <select id="class_id" name="class_id" onchange="filterStudentsByClass(); this.form.submit()">
                <option value="">All classes</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>
                        {{ $class->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="student_id">Student</label>
            <select id="student_id" name="student_id" onchange="this.form.submit()">
                <option value="">All students</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" data-class-id="{{ $student->class_id }}"
                            @selected(request('student_id') == $student->id)>
                        {{ $student->name }} ({{ $student->classRoom->name ?? '—' }})
                    </option>
                @endforeach
            </select>
        </div>
    </form>
</div>

<script>
    function filterStudentsByClass() {
        const classId = document.getElementById('class_id').value;
        const studentSelect = document.getElementById('student_id');
        const options = studentSelect.querySelectorAll('option[data-class-id]');

        // Reset student selection when class changes
        studentSelect.value = '';

        options.forEach(option => {
            if (!classId || option.dataset.classId === classId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    }

    // Apply filter on page load if a class is already selected
    document.addEventListener('DOMContentLoaded', filterStudentsByClass);
</script>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>N.O</th>
                <th>Student</th>
                <th>Class</th>
                <th>Date</th>
                <th>Requested By</th>
                <th>Reason</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($permissions as $permission)
                <tr>
                    <td>{{ $loop->iteration + ($permissions->currentPage() - 1) * $permissions->perPage() }}</td>
                    <td><strong>{{ $permission->student->name }}</strong></td>
                    <td>{{ $permission->student->classRoom->name ?? '—' }}</td>
                    <td>{{ $permission->attendance_date->format('D, d M Y') }}</td>
                    <td>{{ $permission->requested_by }}
                        <span class="small muted">({{ $permission->requested_by_type }})</span>
                    </td>
                    <td>{{ $permission->reason }}</td>
                    <td><x-status-badge :status="$permission->status" /></td>
                    <td>
                        <a href="{{ route('admin.permissions.show', $permission) }}" class="btn btn-sm btn-green">Review</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No permission requests found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">
        {{ $permissions->links() }}
    </div>
</div>
@endsection