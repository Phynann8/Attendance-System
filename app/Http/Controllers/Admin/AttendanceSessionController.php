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
     * Reopen or request reopening of a submitted attendance session.
     * - Teachers request reopening (reopen_status = pending, waiting for confirmation).
     * - Student Affairs, Admin, or Super Admin directly confirm and reopen.
     */
    public function reopen(Request $request, AttendanceSession $session): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $session->classRoom->campus_id && ! $user->hasCampusAccess($session->classRoom->campus_id)) {
            abort(403, 'You do not have permission to access an attendance session from another campus.');
        }

        // Only the assigned teacher, Student Affairs, Admin, or Super Admin can initiate
        if (! $user->canApproveSessionReopen() && $session->teacher_id !== $user->id) {
            abort(403, 'You are not authorized to request reopening for this class session.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            if ($user->canApproveSessionReopen()) {
                // Student Affairs, Admin, or Super Admin can directly confirm and allow
                AttendanceService::reopenSession($session, $user, $validated['reason']);

                return back()->with('success', "Attendance session #{$session->id} ({$session->classRoom->name}) has been reopened for amendment.");
            }

            // Teacher: Submit request, awaiting confirmation from Student Affairs, Admin, or Super Admin
            AttendanceService::requestReopenSession($session, $user, $validated['reason']);

            return back()->with('success', 'Reopen request submitted successfully. Waiting for confirmation from Student Affairs, Admin, or Super Admin.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Student Affairs, Admin, or Super Admin confirms and allows the reopen request.
     */
    public function approve(Request $request, AttendanceSession $session): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canApproveSessionReopen()) {
            abort(403, 'Only Student Affairs, Admin, or Super Admin can confirm and allow session reopening.');
        }

        if (! $user->isSuperAdmin() && $session->classRoom->campus_id && ! $user->hasCampusAccess($session->classRoom->campus_id)) {
            abort(403, 'You do not have permission to approve an attendance session from another campus.');
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            AttendanceService::approveReopenSession($session, $user, $validated['note'] ?? null);

            return back()->with('success', "Reopen request confirmed and allowed for {$session->classRoom->name}. Attendance is now open for amendment.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Student Affairs, Admin, or Super Admin rejects the reopen request.
     */
    public function reject(Request $request, AttendanceSession $session): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canApproveSessionReopen()) {
            abort(403, 'Only Student Affairs, Admin, or Super Admin can reject session reopening.');
        }

        if (! $user->isSuperAdmin() && $session->classRoom->campus_id && ! $user->hasCampusAccess($session->classRoom->campus_id)) {
            abort(403, 'You do not have permission to reject an attendance session from another campus.');
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            AttendanceService::rejectReopenSession($session, $user, $validated['note'] ?? null);

            return back()->with('warning', "Reopen request for {$session->classRoom->name} has been rejected.");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
