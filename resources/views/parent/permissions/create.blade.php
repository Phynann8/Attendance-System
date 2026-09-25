@extends('layouts.app')

@section('title', __('Request Leave'))

@section('content')
<div class="page-title">{{ __('Request Leave') }}</div>
<div class="page-sub">{{ __('Request leave before class.') }}</div>

<div class="alert alert-info">
    {{ __('Submit the request with the valid reason.') }}
</div>

<div class="card" style="max-width:680px; margin: auto">
    <form method="POST" action="{{ route('parent.permissions.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="student_id">{{ __('Student Name') }} <span class="required">*</span></label>
            <select id="student_id" name="student_id" required>
                <option value="">{{ __('Select Student') }}</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                        {{ $student->name }} — {{ $student->classRoom->name ?? __('no class') }}
                    </option>
                @endforeach
            </select>
            @error('student_id')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="attendance_date">{{ __('Class Date (absence)') }} <span class="required">*</span></label>
            <input type="date" id="attendance_date" name="attendance_date"
                   value="{{ old('attendance_date', now()->format('Y-m-d')) }}" required
                   min="{{ now()->format('Y-m-d') }}">
            @error('attendance_date')<div class="error">{{ $message }}</div>@enderror
        </div>

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
            <input type="text" id="reason" name="reason" placeholder="{{ __('e.g. Doctor appointment, High fever') }}" value="{{ old('reason') }}" required>
            @error('reason')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="detail_description">{{ __('Detail Description') }} <span class="form-optional">({{ __('detailed explanation') }})</span></label>
            <textarea id="detail_description" name="detail_description" rows="3" placeholder="{{ __('Explain the situation in detail...') }}">{{ old('detail_description') }}</textarea>
            @error('detail_description')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="evidence">{{ __('Evidence') }} <span class="form-optional">({{ __('optional') }})</span></label>
            <input type="file" id="evidence" name="evidence" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            <div class="form-hint">{{ __('e.g. medical appointment document.') }}</div>
            @error('evidence')<div class="error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn">{{ __('Submit request') }}</button>
    </form>
</div>
@endsection