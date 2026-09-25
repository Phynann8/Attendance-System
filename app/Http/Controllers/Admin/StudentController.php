<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $campusId = $request->user()->activeCampusId();
        $query = Student::with(['classRoom', 'campus'])->orderBy('name');

        if ($campusId) {
            $query->where('campus_id', $campusId);
        } elseif ($request->filled('campus_id') && ($request->user()->isSuperAdmin() || $request->user()->isAdmin())) {
            $query->where('campus_id', (int) $request->input('campus_id'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('khmer_name', 'like', "%{$q}%")
                    ->orWhere('student_code', 'like', "%{$q}%")
                    ->orWhere('parent_phone', 'like', "%{$q}%");
            });
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (int) $request->input('class_id'));
        }

        $classesQuery = ClassRoom::orderBy('name');
        if ($campusId) {
            $classesQuery->where('campus_id', $campusId);
        } elseif ($request->filled('campus_id')) {
            $classesQuery->where('campus_id', (int) $request->input('campus_id'));
        }

        return view('admin.students.index', [
            'students' => $query->paginate(12)->withQueryString(),
            'classes' => $classesQuery->get(),
            'campuses' => Campus::where('is_active', true)->ordered()->get(),
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

    public function show(Student $student, Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $student->campus_id && (int) $student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to view students from another campus.');
        }

        $student->load(['classRoom.campus', 'permissions', 'parentUser', 'campus']);

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
