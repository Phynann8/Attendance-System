@extends('layouts.app')

@section('title', __('Add Class'))

@section('content')
<div class="page-title">{{ __('Add Class') }}</div>
<div class="page-sub">{{ __('Create a classroom and assign its homeroom teacher.') }}</div>

<div class="card" style="max-width:620px; margin: auto;">
    <form method="POST" action="{{ route('admin.classes.store') }}">
        @csrf
        <div class="form-row">
            <div class="form-group">
                <label for="name">{{ __('Class name') }} *</label>
                <input id="name" name="name" placeholder="{{ __('e.g. 10A') }}" value="{{ old('name') }}" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="grade">{{ __('Grade') }}</label>
                <input id="grade" name="grade" placeholder="{{ __('e.g. Grade 10') }}" value="{{ old('grade') }}">
            </div>
        </div>

        <div class="form-group">
            <label for="teacher_id">{{ __('Homeroom teacher') }}</label>
            <select id="teacher_id" name="teacher_id">
                <option value="">{{ __('— none —') }}</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn">{{ __('Save Class') }}</button>
        <a href="{{ route('admin.classes.index') }}" class="btn btn-sm btn-outline">{{ __('Cancel') }}</a>
    </form>
</div>
@endsection