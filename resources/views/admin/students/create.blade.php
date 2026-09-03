@extends('layouts.app')

@section('title', 'Add Student')

@section('content')
<div class="page-title">Add Student</div>
<div class="page-sub">Create a student record and link the parent's contact details.</div>

<div class="card" style="max-width:720px">
    <form method="POST" action="{{ route('admin.students.store') }}">
        @csrf
        <div class="form-group">
            <label for="name">Student name *</label>
            <input id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="class_id">Class *</label>
            <select id="class_id" name="class_id" required>
                <option value="">Select class…</option>
                @foreach(\App\Models\ClassRoom::orderBy('name')->get() as $class)
                    <option value="{{ $class->id }}" @selected(old('class_id') == $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
            @error('class_id')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="parent_name">Parent name</label>
                <input id="parent_name" name="parent_name" value="{{ old('parent_name') }}">
            </div>
            <div class="form-group">
                <label for="parent_phone">Parent phone</label>
                <input id="parent_phone" name="parent_phone" value="{{ old('parent_phone') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="parent_email">Parent email</label>
                <input id="parent_email" name="parent_email" type="email" value="{{ old('parent_email') }}">
            </div>
            <div class="form-group">
                <label for="parent_user_id">Link parent account (optional)</label>
                <select id="parent_user_id" name="parent_user_id">
                    <option value="">— none —</option>
                    @foreach(\App\Models\User::where('role', 'parent')->orderBy('name')->get() as $user)
                        <option value="{{ $user->id }}" @selected(old('parent_user_id') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="btn">Save Student</button>
        <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-outline">Cancel</a>
    </form>
</div>
@endsection