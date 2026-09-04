@extends('layouts.app')

@section('title', 'Request Leave')

@section('content')
<div class="page-title">Request Leave</div>
<div class="page-sub">Request leave before class.</div>

<div class="alert alert-info">
    Submit the request with the valid reason.
</div>

<div class="card" style="max-width:680px; margin: auto">
    <form method="POST" action="{{ route('parent.permissions.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="student_id">Student Name <font color="red">*</font></label>
            <select id="student_id" name="student_id" required>
                <option value="">Select Student</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                        {{ $student->name }} — {{ $student->classRoom->name ?? 'no class' }}
                    </option>
                @endforeach
            </select>
            @error('student_id')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="attendance_date">Class Date (absence) <font color="red">*</font></label>
            <input type="date" id="attendance_date" name="attendance_date"
                   value="{{ old('attendance_date', now()->format('Y-m-d')) }}" required
                   min="{{ now()->format('Y-m-d') }}">
            @error('attendance_date')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="reason">Reason <span style="color: #ff0505;">*</span></label>
            <textarea id="reason" name="reason" rows="3" placeholder="e.g. Medical appointment" required>{{ old('reason') }}</textarea>
            @error('reason')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="evidence">Evidence <span style="color: #2d09ce;">(optional)</span></label>
            <input type="file" id="evidence" name="evidence" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            <div class="form-hint">e.g. medical appointment document.</div>
            @error('evidence')<div class="error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn">Submit request</button>
    </form>
</div>
@endsection