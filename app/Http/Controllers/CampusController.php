<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampusController extends Controller
{
    /**
     * Switch active campus for the current user's session (Super Admin).
     */
    public function switchCampus(Request $request): RedirectResponse
    {
        $user = $request->user();

        $canSwitch = $user->isSuperAdmin()
            || $user->hasPermission('campus.switch')
            || count($user->assignedCampusIds()) > 1;

        if (! $canSwitch) {
            abort(403, 'You do not have permission to switch campuses.');
        }

        $campusId = $request->input('campus_id');

        if ($campusId === '' || $campusId === 'all' || $campusId === null) {
            session()->forget('active_campus_id');

            return back()->with('success', $user->isSuperAdmin() ? 'Viewing all campuses.' : 'Viewing all assigned campuses.');
        }

        $targetId = (int) $campusId;
        if (! $user->isSuperAdmin() && ! $user->hasCampusAccess($targetId)) {
            abort(403, 'You do not have permission to access this campus.');
        }

        $campus = Campus::findOrFail($targetId);
        session(['active_campus_id' => $campus->id]);

        return back()->with('success', "Switched to campus: {$campus->name_en} ({$campus->code}).");
    }
}
