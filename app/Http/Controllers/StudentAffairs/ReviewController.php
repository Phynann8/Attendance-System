<?php

namespace App\Http\Controllers\StudentAffairs;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

class ReviewController extends Controller
{
    /**
     * Absent students for submitted sessions that Student Affairs must verify.
     */
    public function index(Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();

        $casesQuery = Attendance::with(['student.classRoom', 'session.classRoom', 'session.teacher'])
            ->where('status', Attendance::STATUS_ABSENT)
            ->where('case_status', Attendance::CASE_PENDING)
            ->whereHas('session', fn ($q) => $q->where('status', AttendanceSession::STATUS_SUBMITTED));

        $lateQuery = Attendance::where('final_status', Attendance::FINAL_LATE)
            ->whereDate('finalized_at', today());

        $escalatedQuery = Attendance::where('case_status', Attendance::CASE_ESCALATED)
            ->whereNull('final_status');

        if ($userCampusId) {
            $casesQuery->forCampus($userCampusId);
            $lateQuery->forCampus($userCampusId);
            $escalatedQuery->forCampus($userCampusId);
        }

        $cases = $casesQuery->get()
            ->sortByDesc(fn (Attendance $a) => $a->session->session_date->format('Y-m-d').' '.$a->student->name);

        return view('student-affairs.review.index', [
            'cases' => $cases,
            'lateToday' => $lateQuery->count(),
            'escalatedCount' => $escalatedQuery->count(),
        ]);
    }

    public function markArrived(Request $request, Attendance $attendance)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $attendance->student?->campus_id && (int) $attendance->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to review attendance from another campus.');
        }

        $request->validate([
            'arrived_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        try {
            AttendanceService::recordLateArrival($attendance, $request->user(), Carbon::parse($request->input('arrived_at')));

            return back()->with('success', 'Arrival recorded — late case closed.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function escalate(Request $request, Attendance $attendance)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $attendance->student?->campus_id && (int) $attendance->student->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to escalate attendance from another campus.');
        }

        try {
            AttendanceService::escalateNoShow($attendance, $request->user());

            return back()->with('warning', 'Student did not arrive — case escalated to Admin.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
