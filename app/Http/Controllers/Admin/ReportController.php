<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Date-range and multi-filter attendance reporting engine with DB-level aggregation.
     */
    public function index(Request $request)
    {
        $parsed = $this->parseReportFilters($request);
        $startDate = $parsed['startDate'];
        $endDate = $parsed['endDate'];
        $classId = $parsed['classId'];
        $finalStatus = $parsed['finalStatus'];
        $subject = $parsed['subject'];
        $periodNumber = $parsed['periodNumber'];
        $preset = $parsed['preset'];

        $user = $request->user();
        $userCampusId = $user->activeCampusId();
        $assignedCampusIds = $user->assignedCampusIds();

        if ($userCampusId) {
            $campusId = $userCampusId;
        } elseif ($request->filled('campus_id')) {
            $filterCampus = (int) $request->input('campus_id');
            $campusId = ($user->isSuperAdmin() || $user->hasCampusAccess($filterCampus)) ? $filterCampus : null;
        } else {
            $campusId = null;
        }

        $classesQuery = ClassRoom::orderBy('name');
        if ($campusId) {
            $classesQuery->forCampus($campusId);
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $classesQuery->whereIn('campus_id', $assignedCampusIds);
        }
        $classes = $classesQuery->get();

        $campuses = ($user->isSuperAdmin() || empty($assignedCampusIds))
            ? Campus::where('is_active', true)->ordered()->get()
            : Campus::whereIn('id', $assignedCampusIds)->where('is_active', true)->ordered()->get();

        $subjectsQuery = ClassSchedule::query();
        if ($campusId) {
            $subjectsQuery->whereHas('classRoom', fn ($q) => $q->where('campus_id', $campusId));
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $subjectsQuery->whereHas('classRoom', fn ($q) => $q->whereIn('campus_id', $assignedCampusIds));
        }
        $availableSubjects = $subjectsQuery->distinct()->orderBy('subject')->pluck('subject')->filter()->values();

        $periodsQuery = ClassSchedule::query();
        if ($campusId) {
            $periodsQuery->whereHas('classRoom', fn ($c) => $c->where('campus_id', $campusId));
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $periodsQuery->whereHas('classRoom', fn ($c) => $c->whereIn('campus_id', $assignedCampusIds));
        }
        $availablePeriods = $periodsQuery->distinct()->orderBy('period_number')->pluck('period_number')->filter()->values();

        // Build base query scoped to campus, dates, and filters
        $baseQuery = $this->buildAttendanceQuery(
            user: $user,
            campusId: $campusId,
            assignedCampusIds: $assignedCampusIds,
            startDate: $startDate,
            endDate: $endDate,
            classId: $classId,
            finalStatus: $finalStatus,
            subject: $subject,
            periodNumber: $periodNumber
        );

        // 1. DB-Level Aggregation: Compute summary counts directly in SQL (Story 34)
        $summaryRow = (clone $baseQuery)
            ->selectRaw("
                SUM(CASE WHEN attendances.status = 'permission' THEN 1 ELSE 0 END) as permission_count,
                SUM(CASE WHEN attendances.final_status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendances.final_status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN attendances.final_status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                SUM(CASE WHEN attendances.final_status = 'absent_without_permission' THEN 1 ELSE 0 END) as unexcused_count,
                SUM(CASE WHEN attendances.final_status IS NULL AND attendances.status IS NOT NULL THEN 1 ELSE 0 END) as unresolved_count
            ")
            ->first();

        $summary = [
            'present' => (int) ($summaryRow->present_count ?? 0),
            'late' => (int) ($summaryRow->late_count ?? 0),
            'excused' => (int) ($summaryRow->excused_count ?? 0),
            'absent_without_permission' => (int) ($summaryRow->unexcused_count ?? 0),
            'permission' => (int) ($summaryRow->permission_count ?? 0),
            'unresolved' => (int) ($summaryRow->unresolved_count ?? 0),
        ];

        // 2. DB-Level Aggregation: Subject Breakdown with SQL GROUP BY (Story 34)
        $breakdownRows = (clone $baseQuery)
            ->selectRaw("
                COALESCE(class_schedules.subject, 'Daily Homeroom') as subj_name,
                CASE WHEN attendance_sessions.class_schedule_id IS NULL THEN 1 ELSE 0 END as is_hr,
                COALESCE(class_schedules.period_number, 0) as period_num,
                COUNT(DISTINCT attendance_sessions.id) as sessions_count,
                COUNT(attendances.id) as total,
                SUM(CASE WHEN attendances.final_status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN attendances.final_status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN attendances.final_status = 'excused' THEN 1 ELSE 0 END) as excused,
                SUM(CASE WHEN attendances.final_status = 'absent_without_permission' THEN 1 ELSE 0 END) as unexcused,
                SUM(CASE WHEN attendances.final_status IS NULL AND attendances.status IS NOT NULL THEN 1 ELSE 0 END) as pending
            ")
            ->groupBy('subj_name', 'is_hr', 'period_num')
            ->get();

        $subjectBreakdown = [];
        foreach ($breakdownRows as $item) {
            $subjKey = $item->subj_name;
            $total = (int) $item->total;
            $present = (int) $item->present;
            $late = (int) $item->late;
            $rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

            $subjectBreakdown[$subjKey] = [
                'name' => $subjKey,
                'is_homeroom' => (bool) $item->is_hr,
                'period' => $item->is_hr ? 'Homeroom' : "Period #{$item->period_num}",
                'sessions_count' => (int) $item->sessions_count,
                'total' => $total,
                'present' => $present,
                'late' => $late,
                'excused' => (int) $item->excused,
                'unexcused' => (int) $item->unexcused,
                'pending' => (int) $item->pending,
                'attendance_rate' => $rate,
            ];
        }

        uasort($subjectBreakdown, function ($a, $b) {
            if ($a['is_homeroom']) {
                return -1;
            }
            if ($b['is_homeroom']) {
                return 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        // 3. Database-Level Pagination for rows table (Story 34)
        $rows = (clone $baseQuery)
            ->select('attendances.*')
            ->with([
                'student.campus',
                'session.classRoom.campus',
                'session.schedule',
                'finalizer',
            ])
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->orderBy('attendance_sessions.session_date', 'desc')
            ->orderBy('classes.name', 'asc')
            ->orderBy('students.name', 'asc')
            ->paginate(50)
            ->withQueryString();

        $sessions = collect();
        $date = $startDate;

        return view('admin.reports.index', compact(
            'startDate',
            'endDate',
            'classId',
            'finalStatus',
            'subject',
            'periodNumber',
            'availableSubjects',
            'availablePeriods',
            'preset',
            'classes',
            'campuses',
            'campusId',
            'sessions',
            'rows',
            'summary',
            'subjectBreakdown',
            'date'
        ));
    }

    /**
     * Export attendance report to CSV with streaming chunking to eliminate memory bloat (Story 34).
     */
    public function export(Request $request): StreamedResponse
    {
        $parsed = $this->parseReportFilters($request);
        $startDate = $parsed['startDate'];
        $endDate = $parsed['endDate'];
        $classId = $parsed['classId'];
        $finalStatus = $parsed['finalStatus'];
        $subject = $parsed['subject'];
        $periodNumber = $parsed['periodNumber'];

        $user = $request->user();
        $userCampusId = $user->activeCampusId();
        $assignedCampusIds = $user->assignedCampusIds();

        if ($userCampusId) {
            $campusId = $userCampusId;
        } elseif ($request->filled('campus_id')) {
            $filterCampus = (int) $request->input('campus_id');
            $campusId = ($user->isSuperAdmin() || $user->hasCampusAccess($filterCampus)) ? $filterCampus : null;
        } else {
            $campusId = null;
        }

        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();
        $filename = $startStr === $endStr
            ? "attendance-report-{$startStr}.csv"
            : "attendance-report-{$startStr}-to-{$endStr}.csv";

        $baseQuery = $this->buildAttendanceQuery(
            user: $user,
            campusId: $campusId,
            assignedCampusIds: $assignedCampusIds,
            startDate: $startDate,
            endDate: $endDate,
            classId: $classId,
            finalStatus: $finalStatus,
            subject: $subject,
            periodNumber: $periodNumber
        );

        $streamQuery = (clone $baseQuery)
            ->select('attendances.*')
            ->with([
                'student',
                'session.classRoom',
                'session.schedule',
                'finalizer',
            ])
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->orderBy('attendance_sessions.session_date', 'desc')
            ->orderBy('classes.name', 'asc')
            ->orderBy('students.name', 'asc');

        return response()->streamDownload(function () use ($streamQuery) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Date',
                'Class',
                'Period',
                'Subject',
                'Student Name',
                'Teacher Mark',
                'Arrival Time',
                'Minutes Late',
                'Final Status',
                'Finalized By',
                'Admin Note / Reason',
            ]);

            // Stream records in memory-safe chunks (Story 34)
            $streamQuery->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $attendance) {
                    $session = $attendance->session;
                    fputcsv($handle, [
                        $session ? $session->session_date->format('Y-m-d') : '—',
                        $session?->classRoom?->name ?? '—',
                        $session?->schedule ? "#{$session->schedule->period_number}" : 'Homeroom',
                        $session?->schedule ? $session->schedule->subject : 'Daily Homeroom',
                        $attendance->student->name ?? '—',
                        $attendance->status ? ucfirst($attendance->status) : '—',
                        $attendance->arrived_at?->format('H:i') ?? '—',
                        $attendance->minutes_late ?? '—',
                        $attendance->final_status ? ucfirst(str_replace('_', ' ', $attendance->final_status)) : ($attendance->status ? 'Pending' : '—'),
                        $attendance->finalizer->name ?? '—',
                        $attendance->admin_note ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Build unified attendance query with all joined relationships and filters.
     */
    private function buildAttendanceQuery(
        User $user,
        ?int $campusId,
        array $assignedCampusIds,
        Carbon $startDate,
        Carbon $endDate,
        ?int $classId,
        ?string $finalStatus,
        ?string $subject,
        ?int $periodNumber
    ): Builder {
        $query = Attendance::query()
            ->join('attendance_sessions', 'attendances.attendance_session_id', '=', 'attendance_sessions.id')
            ->join('classes', 'attendance_sessions.class_id', '=', 'classes.id')
            ->leftJoin('class_schedules', 'attendance_sessions.class_schedule_id', '=', 'class_schedules.id')
            ->whereDate('attendance_sessions.session_date', '>=', $startDate->toDateString())
            ->whereDate('attendance_sessions.session_date', '<=', $endDate->toDateString());

        if ($campusId) {
            $query->where('classes.campus_id', $campusId);
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $query->whereIn('classes.campus_id', $assignedCampusIds);
        }

        if ($classId) {
            $query->where('attendance_sessions.class_id', $classId);
        }

        if ($subject) {
            if ($subject === 'homeroom') {
                $query->whereNull('attendance_sessions.class_schedule_id');
            } else {
                $query->where('class_schedules.subject', $subject);
            }
        }

        if ($periodNumber) {
            $query->where('class_schedules.period_number', $periodNumber);
        }

        if ($finalStatus) {
            if ($finalStatus === 'pending') {
                $query->whereNull('attendances.final_status');
            } else {
                $query->where('attendances.final_status', $finalStatus);
            }
        }

        return $query;
    }

    /**
     * Parse date range preset and multi-filter inputs.
     *
     * @return array<string, mixed>
     */
    private function parseReportFilters(Request $request): array
    {
        $today = Carbon::today();
        $preset = $request->input('preset', 'today');

        [$startDate, $endDate] = match ($preset) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'custom' => [
                $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : $today->copy()->startOfMonth(),
                $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : $today,
            ],
            default => [
                $request->filled('date') ? Carbon::parse($request->input('date')) : (
                    $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : $today
                ),
                $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : (
                    $request->filled('date') ? Carbon::parse($request->input('date')) : $today
                ),
            ],
        };

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'classId' => $request->filled('class_id') ? (int) $request->input('class_id') : null,
            'finalStatus' => $request->filled('final_status') ? $request->input('final_status') : null,
            'subject' => $request->filled('subject') ? $request->input('subject') : null,
            'periodNumber' => $request->filled('period_number') ? (int) $request->input('period_number') : null,
            'preset' => $preset,
        ];
    }
}
