<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * End-of-day report - mirrors the summary table in the spec (section 21).
     */
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $sessions = AttendanceSession::with(['classRoom', 'teacher', 'attendances.student'])
            ->whereDate('session_date', $date->toDateString())
            ->orderBy('class_id')
            ->get();

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
                    $summary[$attendance->final_status]++;
                } elseif ($attendance->status) {
                    $summary['unresolved']++;
                }
            }
        }

        $rows = $rows->sort(function ($a, $b) {
            return [$a->session->classRoom->name, $a->student->name]
                <=> [$b->session->classRoom->name, $b->student->name];
        });

        return view('admin.reports.index', compact('date', 'rows', 'summary', 'sessions'));
    }
}