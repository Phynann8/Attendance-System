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

        // Only super admin or authorized multi-campus users can switch active campus
        if (! $user->isSuperAdmin() && ! $user->hasPermission('campus.switch')) {
            abort(403, 'You do not have permission to switch campuses.');
        }

        $campusId = $request->input('campus_id');

        if ($campusId === '' || $campusId === 'all' || $campusId === null) {
            session()->forget('active_campus_id');

            return back()->with('success', 'Viewing all campuses.');
        }

        $campus = Campus::findOrFail((int) $campusId);
        session(['active_campus_id' => $campus->id]);

        return back()->with('success', "Switched to campus: {$campus->name_en} ({$campus->code}).");
    }
}
