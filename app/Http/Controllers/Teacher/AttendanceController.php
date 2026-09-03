<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use RuntimeException;

class AttendanceController extends Controller
{
    public function history(Request $request)
    {
        $sessions = AttendanceSession::with(['classRoom', 'attendances'])
            ->where('teacher_id', $request->user()->id)
            ->orderByDesc('session_date')
            ->get();

        return view('teacher.attendance.history', compact('sessions'));
    }

    public function open(ClassRoom $class, Request $request)
    {
        abort_unless($class->teacher_id === $request->user()->id, 403, 'You are not the teacher of this class.');

        try {
            $session = AttendanceService::openSession($class, $request->user());

            return redirect()
                ->route('teacher.attendance.mark', $session)
                ->with('success', "Attendance opened for {$class->name} on {$session->session_date->format('D, d M Y')}. Approved permissions are locked.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function mark(AttendanceSession $session, Request $request)
    {
        abort_unless($session->teacher_id === $request->user()->id, 403);

        $session->load(['attendances.student', 'attendances.permission', 'classRoom']);

        return view('teacher.attendance.mark', compact('session'));
    }

    public function save(AttendanceSession $session, Request $request)
    {
        abort_unless($session->teacher_id === $request->user()->id, 403);

        try {
            AttendanceService::saveMarks($session, $request->user(), $request->input('statuses', []));

            return back()->with('success', 'Attendance saved.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submit(AttendanceSession $session, Request $request)
    {
        abort_unless($session->teacher_id === $request->user()->id, 403);

        try {
            AttendanceService::submitSession($session, $request->user());

            return redirect()
                ->route('teacher.attendance.mark', $session)
                ->with('success', 'Attendance submitted. Please send any late student without approval later to Student Affairs');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}