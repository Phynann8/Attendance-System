<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Permission;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return view('dashboards.admin', [
                'pendingPermissions' => Permission::where('status', Permission::STATUS_PENDING)->count(),
                'approvedPermissionsToday' => Permission::where('status', Permission::STATUS_APPROVED)
                    ->whereDate('attendance_date', today())
                    ->count(),
                'openSessions' => AttendanceSession::where('status', AttendanceSession::STATUS_OPEN)->count(),
                'submittedSessionsToday' => AttendanceSession::where('status', AttendanceSession::STATUS_SUBMITTED)
                    ->whereDate('session_date', today())
                    ->count(),
                'pendingAbsenceReviews' => Attendance::where('status', Attendance::STATUS_ABSENT)
                    ->where('case_status', Attendance::CASE_ESCALATED)
                    ->whereNull('final_status')
                    ->count(),
                'lateToday' => Attendance::where('final_status', Attendance::FINAL_LATE)
                    ->whereDate('finalized_at', today())
                    ->count(),
            ]);
        }

        if ($user->isTeacher()) {
            $classes = $user->classes()->get();

            foreach ($classes as $class) {
                $class->todaySession = AttendanceSession::where('class_id', $class->id)
                    ->whereDate('session_date', today())
                    ->first();
                $class->studentCount = $class->activeStudents()->count();
            }

            return view('dashboards.teacher', compact('classes'));
        }

        if ($user->isStudentAffairs()) {
            $pendingCases = Attendance::with(['student.classRoom', 'session'])
                ->where('status', Attendance::STATUS_ABSENT)
                ->where('case_status', Attendance::CASE_PENDING)
                ->whereHas('session', fn ($q) => $q->where('status', AttendanceSession::STATUS_SUBMITTED))
                ->get()
                ->sortByDesc(fn ($a) => $a->session->session_date);

            return view('dashboards.student_affairs', [
                'pendingCases' => $pendingCases,
                'lateToday' => Attendance::where('final_status', Attendance::FINAL_LATE)
                    ->whereDate('finalized_at', today())
                    ->count(),
                'escalatedCount' => Attendance::where('case_status', Attendance::CASE_ESCALATED)
                    ->whereNull('final_status')
                    ->count(),
            ]);
        }

        // Parent
        $students = $user->students()->with('classRoom')->get();
        $permissions = Permission::whereIn('student_id', $students->pluck('id'))
            ->with(['student.classRoom'])
            ->latest()
            ->get();

        $todayAttendance = Attendance::whereIn('student_id', $students->pluck('id'))
            ->whereHas('session', fn ($q) => $q->whereDate('session_date', today()))
            ->with(['session'])
            ->get();

        return view('dashboards.parent', compact('students', 'permissions', 'todayAttendance'));
    }
}