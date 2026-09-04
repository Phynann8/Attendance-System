@extends('layouts.app')

@section('title', 'Assign Permission')

@section('content')
<div class="page-title">Assign Permission</div>
<div class="page-sub">Assign permission to a student</div>

<div class="card" style="max-width:720px; margin: auto">
    <form method="POST" action="{{ route('admin.permissions.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="class_id">Filter by Class</label>
            <select id="class_id" onchange="filterStudentsByClass()">
                <option value="">All classes</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}">
                        {{ $class->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="student_id">Student <span style="color: red;">*</span></label>
            <select id="student_id" name="student_id" required>
                <option value="">Select student…</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" 
                            data-class-id="{{ $student->class_id }}" 
                            @selected(old('student_id') == $student->id)>
                        {{ $student->name }} — {{ $student->classRoom->name ?? 'no class' }}
                    </option>
                @endforeach
            </select>
            @error('student_id')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="attendance_date">Attendance date <span style="color: red;">*</span></label>
                <input type="date" id="attendance_date" name="attendance_date" value="{{ old('attendance_date', now()->format('Y-m-d')) }}" required min="{{ now()->format('Y-m-d') }}">
                @error('attendance_date')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="requested_by">Requested by <span style="color: red;">*</span></label>
                <input type="text" id="requested_by" name="requested_by" placeholder="e.g. Sok Dara (parent)" value="{{ old('requested_by') }}" required>
                @error('requested_by')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label for="reason">Reason <span style="color: red;">*</span></label>
            <textarea id="reason" name="reason" rows="3" placeholder="e.g. Medical appointment" required>{{ old('reason') }}</textarea>
            @error('reason')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="evidence">Evidence <span style="color: #0362b6;">(optional)</span></label>
            <input type="file" id="evidence" name="evidence" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            <div class="form-hint">Medical appointment document or any supporting file.</div>
            @error('evidence')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="admin_note">Admin note <span style="color: #0362b6;">(optional)</span></label>
            <textarea id="admin_note" name="admin_note" rows="2" placeholder="Context for this request…">{{ old('admin_note') }}</textarea>
        </div>

        <button type="submit" class="btn">Create Request (Pending)</button>
        <a href="{{ route('admin.permissions.index') }}" class="btn btn-sm btn-primary">Cancel</a>
    </form>
</div>

<script>
function filterStudentsByClass() {
    const classId = document.getElementById('class_id').value;
    const studentSelect = document.getElementById('student_id');
    const options = studentSelect.querySelectorAll('option[data-class-id]');
    const selectedOption = studentSelect.selectedOptions[0];

    if (selectedOption && selectedOption.dataset.classId && classId && selectedOption.dataset.classId !== classId) {
        studentSelect.value = '';
    }

    options.forEach(option => {
        if (!classId || option.dataset.classId === classId) {
            option.style.display = '';
            option.disabled = false;
        } else {
            option.style.display = 'none';
            option.disabled = true;
        }
    });
}
</script>
@endsection