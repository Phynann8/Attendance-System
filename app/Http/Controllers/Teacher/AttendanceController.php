<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use RuntimeException;

class AttendanceController extends Controller
{
    public function history(Request $request)
    {
        $user = $request->user();
        $query = AttendanceSession::with(['classRoom', 'attendances']);

        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        } elseif ($campusId = $user->activeCampusId()) {
            $query->forCampus($campusId);
        } elseif (! $user->isSuperAdmin() && ! empty($user->assignedCampusIds())) {
            $query->whereHas('classRoom', fn ($q) => $q->whereIn('campus_id', $user->assignedCampusIds()));
        }

        $sessions = $query->orderByDesc('session_date')->get();

        $openableClassesQuery = ClassRoom::query();
        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            $openableClassesQuery->where('teacher_id', $user->id);
        } elseif ($campusId = $user->activeCampusId()) {
            $openableClassesQuery->forCampus($campusId);
        } elseif (! $user->isSuperAdmin() && ! empty($user->assignedCampusIds())) {
            $openableClassesQuery->whereIn('campus_id', $user->assignedCampusIds());
        }

        $openableClasses = $openableClassesQuery
            ->with(['campus', 'teacher'])
            ->whereDoesntHave('attendanceSessions', fn ($q) => $q->whereDate('session_date', today()))
            ->orderBy('name')
            ->get();

        $campuses = ($user->isSuperAdmin() || empty($user->assignedCampusIds()))
            ? Campus::where('is_active', true)->ordered()->get()
            : Campus::whereIn('id', $user->assignedCampusIds())->where('is_active', true)->ordered()->get();

        return view('teacher.attendance.history', compact('sessions', 'openableClasses', 'campuses'));
    }

    public function open(ClassRoom $class, Request $request)
    {
        $user = $request->user();
        $scheduleId = $request->input('schedule_id');
        $schedule = null;

        if ($scheduleId) {
            $schedule = ClassSchedule::where('class_id', $class->id)->findOrFail($scheduleId);
            $effectiveTeacher = $schedule->getActiveTeacherForDate(today());
            abort_unless(
                $effectiveTeacher->id === $user->id || $class->teacher_id === $user->id || $user->isSuperAdmin() || $user->isAdmin(),
                403,
                'You do not have permission to open attendance for this scheduled period.'
            );
        } else {
            abort_unless(
                $class->teacher_id === $user->id || $user->isSuperAdmin() || $user->isAdmin(),
                403,
                'You do not have permission to open attendance for this class.'
            );
        }

        if (! $user->isSuperAdmin() && $class->campus_id && ! $user->hasCampusAccess($class->campus_id)) {
            abort(403, 'You do not have permission to open attendance for a class from another campus.');
        }

        try {
            $session = AttendanceService::openSession($class, $user, today(), $schedule);
            $periodText = $schedule ? " ({$schedule->label()})" : '';

            return redirect()
                ->route('teacher.attendance.mark', $session)
                ->with('success', "Attendance opened for {$class->name}{$periodText} on {$session->session_date->format('D, d M Y')}. Approved permissions are locked.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function mark(AttendanceSession $session, Request $request)
    {
        $user = $request->user();
        $isAuthorizedTeacher = $session->teacher_id === $user->id
            || ($session->classRoom && $session->classRoom->teacher_id === $user->id)
            || ($session->schedule && $session->schedule->getActiveTeacherForDate($session->session_date)->id === $user->id);

        abort_unless(
            $isAuthorizedTeacher || $user->canApproveSessionReopen(),
            403
        );

        if (! $user->isSuperAdmin() && $session->classRoom->campus_id && ! $user->hasCampusAccess($session->classRoom->campus_id)) {
            abort(403, 'You do not have permission to view attendance for a class from another campus.');
        }

        $session->load(['attendances.student', 'attendances.permission', 'classRoom', 'schedule', 'reopenRequester', 'reopenDecider']);

        return view('teacher.attendance.mark', compact('session'));
    }

    public function save(AttendanceSession $session, Request $request)
    {
        $user = $request->user();
        $isAuthorizedTeacher = $session->teacher_id === $user->id
            || ($session->classRoom && $session->classRoom->teacher_id === $user->id)
            || ($session->schedule && $session->schedule->getActiveTeacherForDate($session->session_date)->id === $user->id);

        abort_unless(
            $isAuthorizedTeacher || $user->isSuperAdmin() || $user->isAdmin(),
            403,
            'You do not have permission to save attendance for this session.'
        );

        if (! $user->isSuperAdmin() && $session->classRoom->campus_id && ! $user->hasCampusAccess($session->classRoom->campus_id)) {
            abort(403, 'You do not have permission to save attendance for a class from another campus.');
        }

        try {
            AttendanceService::saveMarks($session, $user, $request->input('statuses', []));

            return back()->with('success', 'Attendance saved.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submit(AttendanceSession $session, Request $request)
    {
        $user = $request->user();
        $isAuthorizedTeacher = $session->teacher_id === $user->id
            || ($session->classRoom && $session->classRoom->teacher_id === $user->id)
            || ($session->schedule && $session->schedule->getActiveTeacherForDate($session->session_date)->id === $user->id);

        abort_unless(
            $isAuthorizedTeacher || $user->isSuperAdmin() || $user->isAdmin(),
            403,
            'You do not have permission to submit attendance for this session.'
        );

        if (! $user->isSuperAdmin() && $session->classRoom->campus_id && ! $user->hasCampusAccess($session->classRoom->campus_id)) {
            abort(403, 'You do not have permission to submit attendance for a class from another campus.');
        }

        try {
            AttendanceService::submitSession($session, $user);

            return redirect()
                ->route('teacher.attendance.mark', $session)
                ->with('success', 'Attendance submitted. Please send any late student without approval later to Student Affairs');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
