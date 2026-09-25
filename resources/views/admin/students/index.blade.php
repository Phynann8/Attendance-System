@extends('layouts.app')

@section('title', 'Students')

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Students') }}</div>
        <div class="page-sub">{{ __('All students in classes.') }}</div>
    </div>
    <a href="{{ route('admin.students.create') }}" class="btn">+ {{ __('Add Student') }}</a>
</div>

<div class="card">
    <form method="GET" class="filter-row">
        @if(auth()->user()->isSuperAdmin() && !session('active_campus_id'))
            <div class="form-group">
                <label for="campus_id">{{ __('Campus') }}</label>
                <select id="campus_id" name="campus_id" onchange="this.form.submit()">
                    <option value="">{{ __('All Campuses (4)') }}</option>
                    @foreach($campuses as $camp)
                        <option value="{{ $camp->id }}" @selected(request('campus_id') == $camp->id)>
                            {{ $camp->code }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="form-group class-filter">
            <label for="class_id">{{ __('Class') }}</label>
            <select id="class_id" name="class_id">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>
                        {{ $class->name }} {{ $class->campus ? '('.$class->campus->code.')' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group search-filter">
            <label for="q">{{ __('Search') }}</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, Khmer, Code, Phone…') }}">
        </div>
        <button class="btn" type="submit">{{ __('Filter') }}</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('N.O') }}</th>
                <th>{{ __('Campus') }}</th>
                <th>{{ __('Student ID') }}</th>
                <th>{{ __('Student Name') }}</th>
                <th>{{ __('Khmer Name') }}</th>
                <th>{{ __('Gender') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Parent') }}</th>
                <th>{{ __('Phone') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                <tr>
                    <td>{{ $loop->iteration + ($students->currentPage() - 1) * $students->perPage() }}</td>
                    <td>
                        @if($student->campus)
                            <span  style="font-size:0.7rem; font-weight:700;">{{ $student->campus->code }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($student->student_code)
                            <span style="padding: 1px 6px; border-radius: 4px; font-size: 0.72rem;">
                                 {{ $student->student_code }}
                            </span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td style="color: #1e293b;">{{ $student->name }}</td>
                    <td>
                        @if($student->khmer_name)
                            <span class="font-khmer" lang="km" style="font-weight: 500; color: #475569;">{{ $student->khmer_name }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($student->gender)
                            <span style="font-size: 0.7rem; padding: 1px 6px;">
                                {{ $student->gender === 'F' ? __('Female') : __('Male') }}
                            </span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($student->classRoom)
                            <span style="font-weight: 600;">{{ $student->classRoom->name }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $student->parent_name ?? '—' }}</td>
                    <td>{{ $student->parent_phone ?? '—' }}</td>
                    <td>
                        @if($student->is_active)
                            <span class="badge badge-green">{{ __('Active') }}</span>
                        @else
                            <span class="badge badge-red">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-eye"></i> {{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty">{{ __('No students found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $students->links() }}</div>
</div>
@endsection