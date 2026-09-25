<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AttendanceSessionController extends Controller
{
    /**
     * Reopen a submitted attendance session for amendment.
     */
    public function reopen(Request $request, AttendanceSession $session): RedirectResponse
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $session->classRoom->campus_id && (int) $session->classRoom->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to reopen an attendance session from another campus.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            AttendanceService::reopenSession($session, $request->user(), $validated['reason']);

            return back()->with('success', "Attendance session #{$session->id} ({$session->classRoom->name}) has been reopened for amendment.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
