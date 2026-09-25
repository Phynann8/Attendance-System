<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Models\Permission;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissions = Permission::with(['student.classRoom'])
            ->whereIn('student_id', $request->user()->students()->pluck('id'))
            ->latest()
            ->get();

        return view('parent.permissions.index', compact('permissions'));
    }

    public function create(Request $request)
    {
        $students = $request->user()->students()->with('classRoom')->orderBy('name')->get();

        abort_if($students->isEmpty(), 403, 'No children are linked to this parent account.');

        return view('parent.permissions.create', compact('students'));
    }

    public function store(StorePermissionRequest $request)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        abort_unless($student->parent_user_id === $request->user()->id, 403);

        $permission = Permission::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'attendance_date' => $data['attendance_date'],
            'requested_by' => $request->user()->name,
            'requested_by_type' => Permission::REQUESTED_BY_PARENT,
            'reason' => $data['reason'],
            'category' => $data['category'] ?? Permission::CATEGORY_OTHER,
            'detail_description' => $data['detail_description'] ?? null,
            'evidence_path' => $request->hasFile('evidence')
                ? $request->file('evidence')->store('permissions/evidence', 'public')
                : null,
            'status' => Permission::STATUS_PENDING,
        ]);

        AuditService::log('permission.created', permissionId: $permission->id, details: [
            'requested_by' => $permission->requested_by,
            'student_id' => $permission->student_id,
            'date' => $permission->attendance_date->format('Y-m-d'),
        ]);

        return redirect()
            ->route('parent.permissions.index')
            ->with('success', 'Permission request sent — the Admin will review it before class.');
    }
}
