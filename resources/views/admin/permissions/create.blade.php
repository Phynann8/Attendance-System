@extends('layouts.app')

@section('title', __('Assign Permission'))

@section('content')
<div class="page-title">{{ __('Assign Permission') }}</div>
<div class="page-sub">{{ __('Assign permission to a student') }}</div>

<div class="card" style="max-width:720px; margin: auto">
    <form method="POST" action="{{ route('admin.permissions.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="class_id">{{ __('Filter by Class') }}</label>
            <select id="class_id" onchange="filterStudentsByClass()">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}">
                        {{ $class->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="student_id">{{ __('Student') }} <span class="required">*</span></label>
            <select id="student_id" name="student_id" required>
                <option value="">{{ __('Select student…') }}</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" 
                            data-class-id="{{ $student->class_id }}" 
                            @selected(old('student_id') == $student->id)>
                        {{ $student->name }} — {{ $student->classRoom->name ?? __('no class') }}
                    </option>
                @endforeach
            </select>
            @error('student_id')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="attendance_date">{{ __('Attendance date') }} <span class="required">*</span></label>
                <input type="date" id="attendance_date" name="attendance_date" value="{{ old('attendance_date', now()->format('Y-m-d')) }}" required min="{{ now()->format('Y-m-d') }}">
                @error('attendance_date')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="requested_by">{{ __('Requested by') }} <span class="required">*</span></label>
                <input type="text" id="requested_by" name="requested_by" placeholder="{{ __('e.g. Sok Dara (parent)') }}" value="{{ old('requested_by') }}" required>
                @error('requested_by')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category">{{ __('Absence Category') }} <span class="required">*</span></label>
                <select id="category" name="category" required>
                    @foreach(\App\Models\Permission::categories() as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', \App\Models\Permission::CATEGORY_MEDICAL) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('category')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="reason">{{ __('Short Reason / Subject') }} <span class="required">*</span></label>
                <input type="text" id="reason" name="reason" placeholder="{{ __('e.g. Doctor appointment, Severe Flu') }}" value="{{ old('reason') }}" required>
                @error('reason')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label for="detail_description">{{ __('Detail Description') }} <span class="form-optional">({{ __('detailed explanation') }})</span></label>
            <textarea id="detail_description" name="detail_description" rows="3" placeholder="{{ __('Provide full context, symptoms, clinic name, or emergency details...') }}">{{ old('detail_description') }}</textarea>
            @error('detail_description')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="evidence">{{ __('Evidence') }} <span class="form-optional">({{ __('optional') }})</span></label>
            <input type="file" id="evidence" name="evidence" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            <div class="form-hint">{{ __('Medical appointment document or any supporting file.') }}</div>
            @error('evidence')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="admin_note">{{ __('Admin note') }} <span class="form-optional">({{ __('optional') }})</span></label>
            <textarea id="admin_note" name="admin_note" rows="2" placeholder="{{ __('Context for this request…') }}">{{ old('admin_note') }}</textarea>
        </div>

        <button type="submit" class="btn">{{ __('Create Request (Pending)') }}</button>
        <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
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