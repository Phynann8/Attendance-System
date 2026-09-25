<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $campusId = $user->activeCampusId();

        if ($user->isSuperAdmin()) {
            $cacheKey = 'super_admin_'.($campusId ?? 'all');
            $stats = CacheService::rememberDashboardStats($cacheKey, function () use ($campusId) {
                $userQuery = User::query();
                $studentQuery = Student::query();
                $classQuery = ClassRoom::query();
                $permQuery = Permission::query();
                $sessionQuery = AttendanceSession::query();

                if ($campusId) {
                    $userQuery->where('campus_id', $campusId);
                    $studentQuery->forCampus($campusId);
                    $classQuery->forCampus($campusId);
                    $permQuery->forCampus($campusId);
                    $sessionQuery->forCampus($campusId);
                }

                return [
                    'totalUsers' => (clone $userQuery)->count(),
                    'activeUsers' => (clone $userQuery)->where('is_active', true)->count(),
                    'inactiveUsers' => (clone $userQuery)->where('is_active', false)->count(),
                    'trashedUsers' => (clone $userQuery)->onlyTrashed()->count(),
                    'totalRoles' => Role::count(),
                    'customRoles' => Role::where('is_system', false)->count(),
                    'totalStudents' => $studentQuery->count(),
                    'totalClasses' => $classQuery->count(),
                    'pendingPermissions' => $permQuery->where('status', Permission::STATUS_PENDING)->count(),
                    'openSessions' => $sessionQuery->where('status', AttendanceSession::STATUS_OPEN)->count(),
                ];
            });

            return view('dashboards.super_admin', array_merge($stats, [
                'recentUsers' => User::with('roleRecord')->when($campusId, fn ($q) => $q->where('campus_id', $campusId))->latest()->take(5)->get(),
                'recentRoles' => Role::withCount('users')->latest()->take(5)->get(),
            ]));
        }

        if ($user->isAdmin()) {
            $cacheKey = 'admin_'.($campusId ?? 'all');
            $stats = CacheService::rememberDashboardStats($cacheKey, function () use ($campusId) {
                $permQuery = Permission::query();
                $sessionQuery = AttendanceSession::query();
                $attQuery = Attendance::query();

                if ($campusId) {
                    $permQuery->forCampus($campusId);
                    $sessionQuery->forCampus($campusId);
                    $attQuery->forCampus($campusId);
                }

                return [
                    'pendingPermissions' => (clone $permQuery)->where('status', Permission::STATUS_PENDING)->count(),
                    'approvedPermissionsToday' => (clone $permQuery)->where('status', Permission::STATUS_APPROVED)
                        ->whereDate('attendance_date', today())
                        ->count(),
                    'openSessions' => (clone $sessionQuery)->where('status', AttendanceSession::STATUS_OPEN)->count(),
                    'submittedSessionsToday' => (clone $sessionQuery)->where('status', AttendanceSession::STATUS_SUBMITTED)
                        ->whereDate('session_date', today())
                        ->count(),
                    'pendingAbsenceReviews' => (clone $attQuery)->where('status', Attendance::STATUS_ABSENT)
                        ->where('case_status', Attendance::CASE_ESCALATED)
                        ->whereNull('final_status')
                        ->count(),
                    'lateToday' => (clone $attQuery)->where('final_status', Attendance::FINAL_LATE)
                        ->whereDate('finalized_at', today())
                        ->count(),
                ];
            });

            return view('dashboards.admin', $stats);
        }

        if ($user->isTeacher()) {
            $classes = $user->classes()->withCount('activeStudents')->get();
            $classIds = $classes->pluck('id');

            $todaySessions = AttendanceSession::whereIn('class_id', $classIds)
                ->whereDate('session_date', today())
                ->get()
                ->keyBy('class_id');

            foreach ($classes as $class) {
                $class->todaySession = $todaySessions->get($class->id);
                $class->studentCount = $class->active_students_count;
            }

            return view('dashboards.teacher', compact('classes'));
        }

        if ($user->isStudentAffairs()) {
            $pendingQuery = Attendance::with(['student.classRoom', 'session'])
                ->where('status', Attendance::STATUS_ABSENT)
                ->where('case_status', Attendance::CASE_PENDING)
                ->whereHas('session', fn ($q) => $q->where('status', AttendanceSession::STATUS_SUBMITTED));

            if ($campusId) {
                $pendingQuery->forCampus($campusId);
            }

            $pendingCases = $pendingQuery->get()
                ->sortByDesc(fn ($a) => $a->session->session_date);

            $cacheKey = 'student_affairs_'.($campusId ?? 'all');
            $stats = CacheService::rememberDashboardStats($cacheKey, function () use ($campusId) {
                $lateQuery = Attendance::where('final_status', Attendance::FINAL_LATE)
                    ->whereDate('finalized_at', today());

                $escalatedQuery = Attendance::where('case_status', Attendance::CASE_ESCALATED)
                    ->whereNull('final_status');

                if ($campusId) {
                    $lateQuery->forCampus($campusId);
                    $escalatedQuery->forCampus($campusId);
                }

                return [
                    'lateToday' => $lateQuery->count(),
                    'escalatedCount' => $escalatedQuery->count(),
                ];
            });

            return view('dashboards.student_affairs', array_merge($stats, [
                'pendingCases' => $pendingCases,
            ]));
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
