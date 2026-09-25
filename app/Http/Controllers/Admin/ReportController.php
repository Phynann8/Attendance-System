<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Date-range and multi-filter attendance reporting engine.
     */
    public function index(Request $request)
    {
        $parsed = $this->parseReportFilters($request);
        $startDate = $parsed['startDate'];
        $endDate = $parsed['endDate'];
        $classId = $parsed['classId'];
        $finalStatus = $parsed['finalStatus'];
        $preset = $parsed['preset'];

        $userCampusId = $request->user()->activeCampusId();
        $campusId = $userCampusId ?? ($request->filled('campus_id') ? (int) $request->input('campus_id') : null);

        $classes = ClassRoom::forCampus($campusId)->orderBy('name')->get();
        $campuses = Campus::where('is_active', true)->ordered()->get();

        $query = AttendanceSession::with([
            'classRoom.campus',
            'teacher',
            'attendances' => function ($q) use ($finalStatus) {
                $q->with(['student.campus', 'finalizer']);
                if ($finalStatus) {
                    if ($finalStatus === 'pending') {
                        $q->whereNull('final_status');
                    } else {
                        $q->where('final_status', $finalStatus);
                    }
                }
            },
        ])
            ->whereDate('session_date', '>=', $startDate->toDateString())
            ->whereDate('session_date', '<=', $endDate->toDateString());

        if ($campusId) {
            $query->whereHas('classRoom', fn ($q) => $q->where('campus_id', $campusId));
        }

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $sessions = $query->orderBy('session_date', 'desc')->orderBy('class_id')->get();

        $summary = [
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'absent_without_permission' => 0,
            'permission' => 0,
            'unresolved' => 0,
        ];

        $rows = collect();

        foreach ($sessions as $session) {
            foreach ($session->attendances as $attendance) {
                $rows->push($attendance);

                if ($attendance->status === Attendance::STATUS_PERMISSION) {
                    $summary['permission']++;
                }

                if ($attendance->final_status) {
                    $summary[$attendance->final_status] = ($summary[$attendance->final_status] ?? 0) + 1;
                } elseif ($attendance->status) {
                    $summary['unresolved']++;
                }
            }
        }

        $rows = $rows->sort(function ($a, $b) {
            return [$b->session->session_date->format('Y-m-d'), $a->session->classRoom->name ?? '', $a->student->name ?? '']
                <=> [$a->session->session_date->format('Y-m-d'), $b->session->classRoom->name ?? '', $b->student->name ?? ''];
        });

        // For backward compatibility
        $date = $startDate;

        return view('admin.reports.index', compact(
            'startDate',
            'endDate',
            'classId',
            'finalStatus',
            'preset',
            'classes',
            'campuses',
            'campusId',
            'sessions',
            'rows',
            'summary',
            'date'
        ));
    }

    /**
     * Export attendance report to CSV with filters applied.
     */
    public function export(Request $request): StreamedResponse
    {
        $parsed = $this->parseReportFilters($request);
        $startDate = $parsed['startDate'];
        $endDate = $parsed['endDate'];
        $classId = $parsed['classId'];
        $finalStatus = $parsed['finalStatus'];

        $userCampusId = $request->user()->activeCampusId();
        $campusId = $userCampusId ?? ($request->filled('campus_id') ? (int) $request->input('campus_id') : null);

        $query = AttendanceSession::with([
            'classRoom.campus',
            'teacher',
            'attendances' => function ($q) use ($finalStatus) {
                $q->with(['student.campus', 'finalizer']);
                if ($finalStatus) {
                    if ($finalStatus === 'pending') {
                        $q->whereNull('final_status');
                    } else {
                        $q->where('final_status', $finalStatus);
                    }
                }
            },
        ])
            ->whereDate('session_date', '>=', $startDate->toDateString())
            ->whereDate('session_date', '<=', $endDate->toDateString());

        if ($campusId) {
            $query->whereHas('classRoom', fn ($q) => $q->where('campus_id', $campusId));
        }

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $sessions = $query->orderBy('session_date', 'desc')->orderBy('class_id')->get();

        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();
        $filename = $startStr === $endStr
            ? "attendance-report-{$startStr}.csv"
            : "attendance-report-{$startStr}-to-{$endStr}.csv";

        return response()->streamDownload(function () use ($sessions) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Date',
                'Class',
                'Student Name',
                'Teacher Mark',
                'Arrival Time',
                'Minutes Late',
                'Final Status',
                'Finalized By',
                'Admin Note / Reason',
            ]);

            foreach ($sessions as $session) {
                foreach ($session->attendances as $attendance) {
                    fputcsv($handle, [
                        $session->session_date->format('Y-m-d'),
                        $session->classRoom->name ?? '—',
                        $attendance->student->name ?? '—',
                        $attendance->status ? ucfirst(str_replace('_', ' ', $attendance->status)) : 'Unmarked',
                        $attendance->arrived_at ? $attendance->arrived_at->format('H:i') : '—',
                        $attendance->minutes_late ?? 0,
                        $attendance->final_status ? ucfirst(str_replace('_', ' ', $attendance->final_status)) : 'Pending',
                        $attendance->finalizer->name ?? '—',
                        $attendance->admin_note ?? $attendance->lock_reason ?? '',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Helper to parse date filters and presets.
     */
    private function parseReportFilters(Request $request): array
    {
        $preset = $request->input('preset');
        $classId = $request->filled('class_id') ? (int) $request->input('class_id') : null;
        $finalStatus = $request->input('final_status');

        if ($preset === 'today') {
            $startDate = Carbon::today();
            $endDate = Carbon::today();
        } elseif ($preset === 'this_week') {
            $startDate = Carbon::now()->startOfWeek();
            $endDate = Carbon::now()->endOfWeek();
        } elseif ($preset === 'this_month') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        } else {
            if ($request->filled('start_date')) {
                $startDate = Carbon::parse($request->input('start_date'));
                $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : $startDate->copy();
            } elseif ($request->filled('date')) {
                $startDate = Carbon::parse($request->input('date'));
                $endDate = $startDate->copy();
            } else {
                $startDate = Carbon::today();
                $endDate = Carbon::today();
            }
        }

        if ($startDate->gt($endDate)) {
            $temp = $startDate;
            $startDate = $endDate;
            $endDate = $temp;
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'classId' => $classId,
            'finalStatus' => $finalStatus,
            'preset' => $preset,
        ];
    }
}
