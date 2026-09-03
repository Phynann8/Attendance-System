@extends('layouts.app')

@section('title', 'Add Class')

@section('content')
<div class="page-title">Add Class</div>
<div class="page-sub">Create a classroom and assign its homeroom teacher.</div>

<div class="card" style="max-width:620px">
    <form method="POST" action="{{ route('admin.classes.store') }}">
        @csrf
        <div class="form-row">
            <div class="form-group">
                <label for="name">Class name *</label>
                <input id="name" name="name" placeholder="e.g. 10A" value="{{ old('name') }}" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="grade">Grade</label>
                <input id="grade" name="grade" placeholder="e.g. Grade 10" value="{{ old('grade') }}">
            </div>
        </div>

        <div class="form-group">
            <label for="teacher_id">Homeroom teacher</label>
            <select id="teacher_id" name="teacher_id">
                <option value="">— none —</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn">Save Class</button>
        <a href="{{ route('admin.classes.index') }}" class="btn btn-sm btn-outline">Cancel</a>
    </form>
</div>
@endsection