<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\ClassSchedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isTeacher() || $user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        $campusId = $user->activeCampusId();

        // Active teachers for dropdown (for admin inspection)
        $teachersQuery = User::where('role', User::ROLE_TEACHER)->where('is_active', true)->with('campuses')->orderBy('name');
        if ($campusId) {
            $teachersQuery->where(function ($q) use ($campusId) {
                $q->where('campus_id', $campusId)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $campusId));
            });
        } elseif (! $user->isSuperAdmin()) {
            $assigned = $user->assignedCampusIds();
            $teachersQuery->where(function ($q) use ($assigned) {
                $q->whereIn('campus_id', $assigned)
                    ->orWhereHas('campuses', fn ($cq) => $cq->whereIn('campuses.id', $assigned));
            });
        }
        $teachers = $teachersQuery->get();

        // Resolve target teacher
        if ($user->isTeacher() && ! ($user->isAdmin() || $user->isSuperAdmin())) {
            $targetTeacher = $user;
        } elseif ($request->filled('teacher_id')) {
            $targetTeacher = User::where('role', User::ROLE_TEACHER)->findOrFail($request->input('teacher_id'));
            if (! $user->isSuperAdmin()) {
                $hasCommon = false;
                foreach ($user->assignedCampusIds() as $cId) {
                    if ($targetTeacher->hasCampusAccess($cId)) {
                        $hasCommon = true;
                        break;
                    }
                }
                if (! $hasCommon) {
                    abort(403, 'You do not have permission to view schedules from another campus.');
                }
            }
        } elseif ($user->isTeacher()) {
            $targetTeacher = $user;
        } else {
            $targetTeacher = $teachers->first() ?? $user;
        }

        // Date & Week calculation
        $dateInput = $request->input('date');
        $currentDate = $dateInput ? Carbon::parse($dateInput) : today();
        $weekStart = $currentDate->copy()->startOfWeek(Carbon::MONDAY);
        $prevWeek = $weekStart->copy()->subWeek()->format('Y-m-d');
        $nextWeek = $weekStart->copy()->addWeek()->format('Y-m-d');
        $todayDate = today()->format('Y-m-d');

        // Check if Saturday schedule exists for this teacher
        $hasSaturday = ClassSchedule::where('teacher_id', $targetTeacher->id)->where('day_of_week', 6)->exists();
        $daysCount = $hasSaturday ? 6 : 5;
        $weekEnd = $weekStart->copy()->addDays($daysCount - 1);

        $days = [];
        for ($i = 0; $i < $daysCount; $i++) {
            $dayDate = $weekStart->copy()->addDays($i);
            $days[$dayDate->dayOfWeekIso] = [
                'iso' => $dayDate->dayOfWeekIso,
                'date' => $dayDate,
                'name' => $dayDate->format('l'),
                'short_name' => $dayDate->format('D'),
                'formatted' => $dayDate->format('d M'),
                'is_today' => $dayDate->isToday(),
            ];
        }

        // Regular schedules for target teacher
        $regularSchedules = ClassSchedule::with(['classRoom.campus'])
            ->where('teacher_id', $targetTeacher->id)
            ->where('is_active', true)
            ->get();

        // Substitutions where target teacher is covering for a colleague this week
        $substitutionsCovering = ScheduleSubstitution::with(['classSchedule.classRoom.campus', 'originalTeacher'])
            ->where('substitute_teacher_id', $targetTeacher->id)
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        // Substitutions where target teacher is covered away by a colleague this week
        $substitutionsCoveredAway = ScheduleSubstitution::with('substituteTeacher')
            ->where('original_teacher_id', $targetTeacher->id)
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy(fn ($sub) => $sub->class_schedule_id.'_'.$sub->session_date->format('Y-m-d'));

        // Max period number
        $allSchedules = $regularSchedules->concat($substitutionsCovering->pluck('classSchedule')->filter());
        $maxPeriod = max($allSchedules->max('period_number') ?? 0, 6);

        // Fetch existing attendance sessions for this week
        $scheduleIds = $allSchedules->pluck('id')->filter()->unique();
        $weekSessions = AttendanceSession::whereIn('class_schedule_id', $scheduleIds)
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy(fn ($s) => $s->class_schedule_id.'_'.$s->session_date->format('Y-m-d'));

        // Typical period times map
        $periodTimes = [];
        foreach ($allSchedules as $s) {
            if ($s->start_time && ! isset($periodTimes[$s->period_number])) {
                $periodTimes[$s->period_number] = $s->timeRange();
            }
        }

        // Build weekly grid matrix [period][dayIso]
        $grid = [];
        for ($period = 1; $period <= $maxPeriod; $period++) {
            $grid[$period] = [];
            foreach ($days as $dayIso => $dayInfo) {
                $dayDateStr = $dayInfo['date']->format('Y-m-d');
                $regular = $regularSchedules->first(fn ($s) => $s->day_of_week === $dayIso && $s->period_number === $period);

                $subIn = $substitutionsCovering->first(function ($sub) use ($dayDateStr, $period) {
                    return $sub->session_date->format('Y-m-d') === $dayDateStr
                        && $sub->classSchedule
                        && $sub->classSchedule->period_number === $period;
                });

                $item = null;
                if ($subIn) {
                    $session = $weekSessions->get($subIn->class_schedule_id.'_'.$dayDateStr);
                    $item = (object) [
                        'schedule' => $subIn->classSchedule,
                        'class' => $subIn->classSchedule->classRoom,
                        'subject' => $subIn->classSchedule->subject,
                        'start_time' => $subIn->classSchedule->start_time,
                        'end_time' => $subIn->classSchedule->end_time,
                        'is_primary' => $subIn->classSchedule->is_primary,
                        'is_substitute' => true,
                        'is_covered' => false,
                        'original_teacher' => $subIn->originalTeacher,
                        'session' => $session,
                        'date' => $dayInfo['date'],
                    ];
                } elseif ($regular) {
                    $subAway = $substitutionsCoveredAway->get($regular->id.'_'.$dayDateStr);
                    $session = $weekSessions->get($regular->id.'_'.$dayDateStr);
                    $item = (object) [
                        'schedule' => $regular,
                        'class' => $regular->classRoom,
                        'subject' => $regular->subject,
                        'start_time' => $regular->start_time,
                        'end_time' => $regular->end_time,
                        'is_primary' => $regular->is_primary,
                        'is_substitute' => false,
                        'is_covered' => (bool) $subAway,
                        'substitute_teacher' => $subAway?->substituteTeacher,
                        'session' => $session,
                        'date' => $dayInfo['date'],
                    ];
                }

                $grid[$period][$dayIso] = $item;
            }
        }

        // Total teaching load for target teacher
        $weeklyPeriodsCount = $regularSchedules->count();

        return view('teacher.schedule.index', compact(
            'targetTeacher',
            'teachers',
            'days',
            'grid',
            'periodTimes',
            'maxPeriod',
            'weekStart',
            'weekEnd',
            'prevWeek',
            'nextWeek',
            'todayDate',
            'weeklyPeriodsCount'
        ));
    }
}
