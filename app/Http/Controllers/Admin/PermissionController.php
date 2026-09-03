<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Permission::with(['student.classRoom', 'approver', 'rejecter'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        $students = Student::with('classRoom')->orderBy('name')->get();
        $classes = \App\Models\ClassRoom::orderBy('name')->get();

        return view('admin.permissions.index', [
            'permissions' => $query->paginate(15)->withQueryString(),
            'students' => $students,
            'classes' => $classes,
        ]);
    }

    public function create()
    {
        return view('admin.permissions.create', [
            'students' => Student::with('classRoom')->orderBy('name')->get(),
            'classes' => ClassRoom::orderBy('name')->get(),
        ]);
    }

    public function store(StorePermissionRequest $request)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        $permission = Permission::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'attendance_date' => $data['attendance_date'],
            'requested_by' => $data['requested_by'],
            'requested_by_type' => $request->user()->isParent()
                ? Permission::REQUESTED_BY_PARENT
                : Permission::REQUESTED_BY_ADMIN,
            'reason' => $data['reason'],
            'evidence_path' => $request->hasFile('evidence')
                ? $request->file('evidence')->store('permissions/evidence', 'public')
                : null,
            'status' => Permission::STATUS_PENDING,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        AuditService::log('permission.created', permissionId: $permission->id, details: [
            'requested_by' => $permission->requested_by,
            'student_id' => $permission->student_id,
            'date' => $permission->attendance_date->format('Y-m-d'),
        ]);

        return redirect()
            ->route('admin.permissions.show', $permission)
            ->with('success', 'Permission request created with status Pending.');
    }

    public function show(Permission $permission)
    {
        $permission->load(['student.classRoom', 'approver', 'rejecter']);

        return view('admin.permissions.show', compact('permission'));
    }

    public function approve(Request $request, Permission $permission)
    {
        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $permission = AttendanceService::approvePermission($permission, $request->user(), $request->input('admin_note'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Permission approved — the teacher will see it as a locked row.');
    }

    public function reject(Request $request, Permission $permission)
    {
        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $permission = AttendanceService::rejectPermission($permission, $request->user(), $request->input('admin_note'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('warning', 'Permission rejected.');
    }
}