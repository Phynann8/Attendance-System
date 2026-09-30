<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FileUploadSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentApiController extends Controller
{
    /**
     * Get children linked to the authenticated parent.
     */
    public function children(Request $request): JsonResponse
    {
        $parent = $request->user();

        $students = Student::with('classRoom')
            ->when(! $parent->isSuperAdmin() && ! $parent->isAdmin(), fn ($q) => $q->where('parent_user_id', $parent->id))
            ->where('is_active', true)
            ->get();

        return response()->json([
            'children' => $students->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'gender' => $s->gender,
                'class' => [
                    'id' => $s->classRoom?->id,
                    'name' => $s->classRoom?->name,
                ],
            ]),
        ]);
    }

    /**
     * Get attendance records for a specific child.
     */
    public function childAttendance(Request $request, int $studentId): JsonResponse
    {
        $parent = $request->user();

        $student = Student::where('id', $studentId)
            ->when(! $parent->isSuperAdmin() && ! $parent->isAdmin(), fn ($q) => $q->where('parent_user_id', $parent->id))
            ->firstOrFail();

        $attendances = Attendance::with('session')
            ->where('student_id', $student->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'records' => $attendances->map(fn ($a) => [
                'id' => $a->id,
                'date' => $a->session?->session_date?->toDateString(),
                'status' => $a->final_status ?? $a->status,
                'minutes_late' => $a->minutes_late,
            ]),
        ]);
    }

    /**
     * Submit a permission request via parent mobile app.
     */
    public function submitPermission(Request $request): JsonResponse
    {
        $parent = $request->user();

        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'attendance_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'in:medical,family_emergency,official_activity,bereavement,unexcused,other'],
            'detail_description' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
        ]);

        $student = Student::where('id', $validated['student_id'])
            ->when(! $parent->isSuperAdmin() && ! $parent->isAdmin(), fn ($q) => $q->where('parent_user_id', $parent->id))
            ->firstOrFail();

        $evidencePath = $request->hasFile('evidence')
            ? FileUploadSecurityService::validateAndStore($request->file('evidence'))
            : null;

        $permission = Permission::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'attendance_date' => $validated['attendance_date'],
            'requested_by' => $parent->name,
            'requested_by_type' => Permission::REQUESTED_BY_PARENT,
            'reason' => $validated['reason'],
            'category' => $validated['category'] ?? Permission::CATEGORY_OTHER,
            'detail_description' => $validated['detail_description'] ?? null,
            'evidence_path' => $evidencePath,
            'status' => Permission::STATUS_PENDING,
        ]);

        AuditService::log('api.permission.created', permissionId: $permission->id, details: [
            'student_id' => $student->id,
            'date' => $permission->attendance_date->format('Y-m-d'),
        ]);

        return response()->json([
            'message' => 'Permission request submitted successfully.',
            'permission' => [
                'id' => $permission->id,
                'status' => $permission->status,
                'attendance_date' => $permission->attendance_date->toDateString(),
                'reason' => $permission->reason,
            ],
        ], 201);
    }
}
