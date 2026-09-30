<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleSubstitutionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        $campusId = $user->activeCampusId();
        $assignedCampusIds = $user->assignedCampusIds();

        $query = ScheduleSubstitution::with([
            'classSchedule.classRoom.campus',
            'classSchedule.teacher.campuses',
            'originalTeacher.campuses',
            'substituteTeacher.campuses',
            'creator',
        ])->orderByDesc('session_date')->orderByDesc('id');

        if ($campusId) {
            $query->whereHas('classSchedule.classRoom', fn ($q) => $q->where('campus_id', $campusId));
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $query->whereHas('classSchedule.classRoom', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
        }

        $substitutions = $query->paginate(20);

        // Schedules available for substitution
        $schedulesQuery = ClassSchedule::with(['classRoom.campus', 'teacher.campuses'])->where('is_active', true);
        if ($campusId) {
            $schedulesQuery->whereHas('classRoom', fn ($q) => $q->where('campus_id', $campusId));
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $schedulesQuery->whereHas('classRoom', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
        }
        $schedules = $schedulesQuery->get()->sortBy(fn ($s) => ($s->classRoom?->name ?? '').' '.$s->day_of_week.' '.$s->period_number);

        // Active teachers
        $teachersQuery = User::where('role', User::ROLE_TEACHER)->where('is_active', true)->with('campuses')->orderBy('name');
        if ($campusId) {
            $teachersQuery->where(function ($q) use ($campusId) {
                $q->where('campus_id', $campusId)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $campusId));
            });
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $teachersQuery->where(function ($q) use ($assignedCampusIds) {
                $q->whereIn('campus_id', $assignedCampusIds)
                    ->orWhereHas('campuses', fn ($cq) => $cq->whereIn('campuses.id', $assignedCampusIds));
            });
        }
        $teachers = $teachersQuery->get();

        return view('admin.substitutions.index', compact('substitutions', 'schedules', 'teachers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        $validated = $request->validate([
            'class_schedule_id' => ['required', 'exists:class_schedules,id'],
            'session_date' => ['required', 'date'],
            'substitute_teacher_id' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $schedule = ClassSchedule::with('classRoom')->findOrFail($validated['class_schedule_id']);

        if (! $user->isSuperAdmin() && $schedule->classRoom?->campus_id && ! $user->hasCampusAccess($schedule->classRoom->campus_id)) {
            abort(403, 'You do not have permission to manage substitutions for another campus.');
        }

        $subTeacher = User::findOrFail($validated['substitute_teacher_id']);
        if (! $subTeacher->isTeacher() || ! $subTeacher->is_active) {
            return back()->with('error', 'The substitute user must be an active teacher.');
        }

        if ($schedule->classRoom?->campus_id && ! $subTeacher->hasCampusAccess($schedule->classRoom->campus_id)) {
            return back()->with('error', 'The substitute teacher must belong to the same campus as the class.');
        }

        if ($schedule->teacher_id === $subTeacher->id) {
            return back()->with('error', 'The selected teacher is already the assigned teacher for this period.');
        }

        $dateFormatted = Carbon::parse($validated['session_date'])->format('Y-m-d');

        $substitution = ScheduleSubstitution::updateOrCreate(
            [
                'class_schedule_id' => $schedule->id,
                'session_date' => $dateFormatted,
            ],
            [
                'original_teacher_id' => $schedule->teacher_id,
                'substitute_teacher_id' => $subTeacher->id,
                'reason' => $validated['reason'] ?: null,
                'created_by' => $user->id,
            ]
        );

        AuditService::log('substitution.created', user: $user, details: [
            'class_id' => $schedule->class_id,
            'class_name' => $schedule->classRoom?->name,
            'period_number' => $schedule->period_number,
            'subject' => $schedule->subject,
            'session_date' => $dateFormatted,
            'original_teacher' => $schedule->teacher?->name,
            'substitute_teacher' => $subTeacher->name,
            'reason' => $validated['reason'] ?: null,
        ]);

        return back()->with('success', "Teacher {$subTeacher->name} assigned as substitute for {$schedule->classRoom?->name} ({$schedule->label()}) on {$dateFormatted}.");
    }

    public function destroy(Request $request, ScheduleSubstitution $substitution): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        if (! $user->isSuperAdmin() && $substitution->classSchedule?->classRoom?->campus_id && ! $user->hasCampusAccess($substitution->classSchedule->classRoom->campus_id)) {
            abort(403, 'You do not have permission to delete substitutions for another campus.');
        }

        $substitution->delete();

        AuditService::log('substitution.deleted', user: $user, details: [
            'substitution_id' => $substitution->id,
            'schedule_id' => $substitution->class_schedule_id,
            'session_date' => $substitution->session_date->format('Y-m-d'),
        ]);

        return back()->with('success', 'Teacher substitution has been cancelled.');
    }
}
