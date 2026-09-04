@extends('layouts.app')

@section('title', 'Students')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Students</div>
        <div class="page-sub">All students in classes.</div>
    </div>
    <a href="{{ route('admin.students.create') }}" class="btn">+ Add Student</a>
</div>

<div class="card">
    <form method="GET" class="filter-row">
        <div class="form-group class-filter">
            <label for="class_id">Class</label>
            <select id="class_id" name="class_id">
                <option value="">All classes</option>
                @foreach(\App\Models\ClassRoom::orderBy('name')->get() as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group search-filter">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Name or phone…">
        </div>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>N.O</th>
                <th>Name</th>
                <th>Class</th>
                <th>Parent</th>
                <th>Phone</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                <tr>
                    <td>{{ $loop->iteration + ($students->currentPage() - 1) * $students->perPage() }}</td>
                    <td><strong>{{ $student->name }}</strong></td>
                    <td>{{ $student->classRoom->name ?? '—' }}</td>
                    <td>{{ $student->parent_name ?? '—' }}</td>
                    <td>{{ $student->parent_phone ?? '—' }}</td>
                    <td>
                        @if($student->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-primary">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $students->links() }}</div>
</div>
@endsection