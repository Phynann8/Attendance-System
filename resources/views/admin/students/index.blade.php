@extends('layouts.app')

@section('title', 'Students')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Students</div>
        <div class="page-sub">All students across classes.</div>
    </div>
    <a href="{{ route('admin.students.create') }}" class="btn">+ Add Student</a>
</div>

<div class="card">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Name or phone…">
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-outline btn-sm" type="submit">Search</button>
        </div>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Class</th>
                <th>Parent</th>
                <th>Phone</th>
                <th>Active</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                <tr>
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
                    <td><a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $students->links() }}</div>
</div>
@endsection