<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with(['classRoom'])->orderBy('name');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('parent_phone', 'like', "%{$q}%");
            });
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (int) $request->input('class_id'));
        }

        return view('admin.students.index', [
            'students' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.students.create');
    }

    public function store(StoreStudentRequest $request)
    {
        Student::create($request->validated());

        return redirect()->route('admin.students.index')->with('success', 'Student added.');
    }

    public function show(Student $student)
    {
        $student->load(['classRoom', 'permissions', 'parentUser']);

        $attendances = Attendance::with(['session.classRoom'])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->get();

        $permissions = Permission::with(['approver'])
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        return view('admin.students.show', compact('student', 'attendances', 'permissions'));
    }
}