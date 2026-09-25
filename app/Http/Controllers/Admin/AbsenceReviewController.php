<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Permission;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class AbsenceReviewController extends Controller
{
    /**
     * Students who were marked Absent, did not arrive (Student Affairs escalated
     * the case) and have no final decision yet. Rule 6 - Admin acts on these only.
     */
    public function index(Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();
        $query = Attendance::with(['student.classRoom', 'session.classRoom', 'permission'])
            ->where('status', Attendance::STATUS_ABSENT)
            ->where('case_status', Attendance::CASE_ESCALATED)
            ->whereNull('final_status');

        if ($userCampusId) {
            $query->forCampus($userCampusId);
        }

        $cases = $query->get()
            ->sortByDesc(fn (Attendance $a) => $a->session->session_date->format('Y-m-d'));

        $existingPermissionCount = Permission::where('status', Permission::STATUS_APPROVED)
            ->whereIn('student_id', $cases->pluck('student_id'))
            ->count();

        return view('admin.absence.index', compact('cases', 'existingPermissionCount'));
    }

    public function show(Attendance $attendance, Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $attendance->student?->campus_id && (int) $attendance->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to view cases from another campus.');
        }

        $attendance->load(['student.classRoom', 'session.classRoom', 'session.teacher', 'permission']);

        $existingPermissions = Permission::with(['approver', 'rejecter'])
            ->where('student_id', $attendance->student_id)
            ->whereDate('attendance_date', $attendance->session->session_date)
            ->get();

        return view('admin.absence.show', compact('attendance', 'existingPermissions'));
    }

    public function decide(Request $request, Attendance $attendance)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $attendance->student?->campus_id && (int) $attendance->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to decide cases from another campus.');
        }
        $request->validate([
            'decision' => ['required', 'in:excused,absent_without_permission'],
            'permission_id' => ['nullable', 'exists:permissions,id'],
            'requested_by' => ['nullable', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if ($request->input('decision') === 'excused') {
                if ($request->filled('permission_id')) {
                    $permission = Permission::findOrFail($request->input('permission_id'));
                    AttendanceService::applyExistingPermission(
                        $attendance,
                        $request->user(),
                        $permission,
                        $request->input('admin_note'),
                    );
                } else {
                    $request->validate([
                        'requested_by' => ['required', 'string', 'max:191'],
                        'reason' => ['required', 'string', 'max:500'],
                    ]);

                    AttendanceService::finalizeExcused(
                        $attendance,
                        $request->user(),
                        $request->input('requested_by'),
                        $request->input('reason'),
                        $request->input('admin_note'),
                    );
                }

                return back()->with('success', 'Final result set: Excused Absence (permission approved).');
            }

            AttendanceService::finalizeAbsentWithoutPermission(
                $attendance,
                $request->user(),
                $request->input('admin_note'),
            );

            return back()->with('warning', 'Final result set: Absent Without Permission.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
