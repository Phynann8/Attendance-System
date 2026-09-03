@extends('layouts.app')

@section('title', 'Classes')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">Classes</div>
        <div class="page-sub">Classrooms, homeroom teachers and student counts.</div>
    </div>
    <a href="{{ route('admin.classes.create') }}" class="btn">+ Add Class</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Grade</th>
                <th>Teacher</th>
                <th>Students</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($classes as $class)
                <tr>
                    <td><strong>{{ $class->name }}</strong></td>
                    <td>{{ $class->grade ?? '—' }}</td>
                    <td>{{ $class->teacher->name ?? '—' }}</td>
                    <td>{{ $class->students_count }}</td>
                    <td><a href="{{ route('admin.classes.show', $class) }}" class="btn btn-sm btn-outline">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No classes yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection