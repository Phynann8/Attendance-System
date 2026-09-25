<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Models\Campus;
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
        $userCampusId = $request->user()->activeCampusId();
        $campusId = $userCampusId ?? ($request->filled('campus_id') ? (int) $request->input('campus_id') : null);

        $query = Permission::with(['student.classRoom', 'approver', 'rejecter'])->latest();

        if ($campusId) {
            $query->forCampus($campusId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        $students = Student::forCampus($campusId)->with('classRoom')->orderBy('name')->get();
        $classes = ClassRoom::forCampus($campusId)->orderBy('name')->get();
        $campuses = Campus::where('is_active', true)->ordered()->get();

        return view('admin.permissions.index', [
            'permissions' => $query->paginate(15)->withQueryString(),
            'students' => $students,
            'classes' => $classes,
            'campuses' => $campuses,
        ]);
    }

    public function create(Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();
        $students = Student::forCampus($userCampusId)->with('classRoom')->orderBy('name')->get();
        $classes = ClassRoom::forCampus($userCampusId)->orderBy('name')->get();

        return view('admin.permissions.create', [
            'students' => $students,
            'classes' => $classes,
        ]);
    }

    public function store(StorePermissionRequest $request)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $student->campus_id && (int) $student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to submit permission requests for students of another campus.');
        }

        $permission = Permission::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'attendance_date' => $data['attendance_date'],
            'requested_by' => $data['requested_by'],
            'requested_by_type' => $request->user()->isParent()
                ? Permission::REQUESTED_BY_PARENT
                : Permission::REQUESTED_BY_ADMIN,
            'reason' => $data['reason'],
            'category' => $data['category'] ?? Permission::CATEGORY_OTHER,
            'detail_description' => $data['detail_description'] ?? null,
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

    public function show(Permission $permission, Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $permission->student?->campus_id && (int) $permission->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to view permission requests from another campus.');
        }

        $permission->load(['student.classRoom', 'approver', 'rejecter']);

        return view('admin.permissions.show', compact('permission'));
    }

    public function approve(Request $request, Permission $permission)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $permission->student?->campus_id && (int) $permission->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to approve permission requests from another campus.');
        }

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
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $permission->student?->campus_id && (int) $permission->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to reject permission requests from another campus.');
        }

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
