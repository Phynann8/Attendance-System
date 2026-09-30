<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSubstitution;
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
            $assignedCampusIds = $user->assignedCampusIds();
            $cacheKey = 'admin_'.($campusId ?? (empty($assignedCampusIds) ? 'all' : implode('_', $assignedCampusIds)));
            $stats = CacheService::rememberDashboardStats($cacheKey, function () use ($campusId, $assignedCampusIds) {
                $permQuery = Permission::query();
                $sessionQuery = AttendanceSession::query();
                $attQuery = Attendance::query();

                if ($campusId) {
                    $permQuery->forCampus($campusId);
                    $sessionQuery->forCampus($campusId);
                    $attQuery->forCampus($campusId);
                } elseif (! empty($assignedCampusIds)) {
                    $permQuery->whereHas('student', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
                    $sessionQuery->whereHas('classRoom', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
                    $attQuery->whereHas('student', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
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
                ->whereNull('class_schedule_id')
                ->whereDate('session_date', today())
                ->get()
                ->keyBy('class_id');

            foreach ($classes as $class) {
                $class->todaySession = $todaySessions->get($class->id);
                $class->studentCount = $class->active_students_count;
            }

            // Period schedules for today (Story 38)
            $today = today();
            $dayOfWeek = $today->dayOfWeekIso; // 1 = Monday, ..., 7 = Sunday

            // 1. Regular schedules for today where teacher_id = user->id
            $regularSchedules = ClassSchedule::with(['classRoom.campus'])
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->where('teacher_id', $user->id)
                ->get();

            // 2. Substituted schedules for today where user is substitute
            $substitutions = ScheduleSubstitution::with(['classSchedule.classRoom.campus', 'originalTeacher'])
                ->whereDate('session_date', $today)
                ->where('substitute_teacher_id', $user->id)
                ->get();

            // Schedules where this teacher was substituted out today
            $coveredSubstitutions = ScheduleSubstitution::with('substituteTeacher')
                ->whereDate('session_date', $today)
                ->whereIn('class_schedule_id', $regularSchedules->pluck('id'))
                ->get()
                ->keyBy('class_schedule_id');

            $todayPeriods = collect();

            foreach ($regularSchedules as $sched) {
                $sub = $coveredSubstitutions->get($sched->id);
                $isCovered = (bool) $sub;
                $todayPeriods->push((object) [
                    'schedule' => $sched,
                    'class' => $sched->classRoom,
                    'period_number' => $sched->period_number,
                    'subject' => $sched->subject,
                    'start_time' => $sched->start_time,
                    'end_time' => $sched->end_time,
                    'is_primary' => $sched->is_primary,
                    'is_substitute' => false,
                    'is_covered' => $isCovered,
                    'substitute_teacher' => $sub?->substituteTeacher,
                    'original_teacher' => null,
                ]);
            }

            foreach ($substitutions as $sub) {
                if ($sub->classSchedule) {
                    $todayPeriods->push((object) [
                        'schedule' => $sub->classSchedule,
                        'class' => $sub->classSchedule->classRoom,
                        'period_number' => $sub->classSchedule->period_number,
                        'subject' => $sub->classSchedule->subject,
                        'start_time' => $sub->classSchedule->start_time,
                        'end_time' => $sub->classSchedule->end_time,
                        'is_primary' => $sub->classSchedule->is_primary,
                        'is_substitute' => true,
                        'is_covered' => false,
                        'original_teacher' => $sub->originalTeacher,
                        'reason' => $sub->reason,
                    ]);
                }
            }

            $todayPeriods = $todayPeriods->sortBy(fn ($p) => ($p->period_number * 1000) + (int) str_replace(':', '', $p->start_time ?? '00:00'))->values();

            $schedIds = $todayPeriods->pluck('schedule.id')->filter()->unique();
            $periodSessions = AttendanceSession::whereIn('class_schedule_id', $schedIds)
                ->whereDate('session_date', $today)
                ->get()
                ->keyBy('class_schedule_id');

            foreach ($todayPeriods as $p) {
                $p->session = $periodSessions->get($p->schedule->id);
                $p->studentCount = $p->class ? $p->class->activeStudents()->count() : 0;
            }

            return view('dashboards.teacher', compact('classes', 'todayPeriods'));
        }

        if ($user->isStudentAffairs()) {
            $assignedCampusIds = $user->assignedCampusIds();
            $pendingQuery = Attendance::with(['student.classRoom', 'session'])
                ->where('status', Attendance::STATUS_ABSENT)
                ->where('case_status', Attendance::CASE_PENDING)
                ->whereHas('session', fn ($q) => $q->where('status', AttendanceSession::STATUS_SUBMITTED));

            if ($campusId) {
                $pendingQuery->forCampus($campusId);
            } elseif (! empty($assignedCampusIds)) {
                $pendingQuery->whereHas('student', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
            }

            $pendingCases = $pendingQuery->get()
                ->sortByDesc(fn ($a) => $a->session->session_date);

            $cacheKey = 'student_affairs_'.($campusId ?? (empty($assignedCampusIds) ? 'all' : implode('_', $assignedCampusIds)));
            $stats = CacheService::rememberDashboardStats($cacheKey, function () use ($campusId, $assignedCampusIds) {
                $lateQuery = Attendance::where('final_status', Attendance::FINAL_LATE)
                    ->whereDate('finalized_at', today());

                $escalatedQuery = Attendance::where('case_status', Attendance::CASE_ESCALATED)
                    ->whereNull('final_status');

                if ($campusId) {
                    $lateQuery->forCampus($campusId);
                    $escalatedQuery->forCampus($campusId);
                } elseif (! empty($assignedCampusIds)) {
                    $lateQuery->whereHas('student', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
                    $escalatedQuery->whereHas('student', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
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
