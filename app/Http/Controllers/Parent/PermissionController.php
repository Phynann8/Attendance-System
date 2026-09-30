<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Models\Permission;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FileUploadSecurityService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Permission::with(['student.classRoom']);

        if (! $user->isSuperAdmin()) {
            $query->whereIn('student_id', $user->students()->pluck('id'));
        } elseif ($campusId = $user->activeCampusId()) {
            $query->forCampus($campusId);
        }

        $permissions = $query->latest()->get();

        return view('parent.permissions.index', compact('permissions'));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $studentsQuery = Student::with('classRoom')->orderBy('name');
            if ($campusId = $user->activeCampusId()) {
                $studentsQuery->forCampus($campusId);
            }
            $students = $studentsQuery->get();
        } else {
            $students = $user->students()->with('classRoom')->orderBy('name')->get();
        }

        abort_if($students->isEmpty(), 403, 'No children are linked to this parent account.');

        return view('parent.permissions.create', compact('students'));
    }

    public function store(StorePermissionRequest $request)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);
        $user = $request->user();

        abort_unless($student->parent_user_id === $user->id || $user->isSuperAdmin() || $user->isAdmin(), 403);

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
                ? FileUploadSecurityService::validateAndStore($request->file('evidence'))
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
