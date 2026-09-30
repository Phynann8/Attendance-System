<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $campusId = $user->activeCampusId();
        $assignedCampusIds = $user->assignedCampusIds();

        $query = AttendanceLog::with([
            'user',
            'attendance.student.classRoom',
            'attendance.student.campus',
            'attendance.session.classRoom.campus',
            'permission.student.campus',
        ]);

        // Campus scoping: non-super-admins only see logs for their campus(es)
        if ($campusId) {
            $this->applyCampusScope($query, [$campusId]);
        } elseif ($request->filled('campus_id') && ($user->isSuperAdmin() || $user->isAdmin())) {
            $filterCampus = (int) $request->input('campus_id');
            if ($user->isSuperAdmin() || $user->hasCampusAccess($filterCampus)) {
                $this->applyCampusScope($query, [$filterCampus]);
            }
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $this->applyCampusScope($query, $assignedCampusIds);
        }

        // Action filter
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Date filter
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        // Search in details or user name
        if ($request->filled('q')) {
            $term = '%'.trim($request->input('q')).'%';
            $query->where(function ($q) use ($term) {
                $q->where('details', 'like', $term)
                    ->orWhere('action', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
            });
        }

        $logs = $query->latest('id')->paginate(25)->withQueryString();

        $actionTypes = AttendanceLog::distinct()->pluck('action')->filter()->values();

        $campuses = ($user->isSuperAdmin() || empty($assignedCampusIds))
            ? Campus::where('is_active', true)->ordered()->get()
            : Campus::whereIn('id', $assignedCampusIds)->where('is_active', true)->ordered()->get();

        return view('admin.audit_logs.index', compact('logs', 'actionTypes', 'campuses'));
    }

    /**
     * Scope audit log query to only include records related to the given campus IDs.
     *
     * @param  Builder  $query
     * @param  array<int>  $campusIds
     */
    private function applyCampusScope($query, array $campusIds): void
    {
        $query->where(function ($q) use ($campusIds) {
            // Logs linked through attendance -> student -> campus
            $q->whereHas('attendance.student', fn ($sq) => $sq->whereIn('campus_id', $campusIds))
                // Logs linked through permission -> student -> campus
                ->orWhereHas('permission.student', fn ($pq) => $pq->whereIn('campus_id', $campusIds))
                // Logs with no attendance/permission (admin actions) -> scope by actor's campus
                ->orWhere(function ($uq) use ($campusIds) {
                    $uq->whereNull('attendance_id')
                        ->whereNull('permission_id')
                        ->whereHas('user', fn ($u) => $u->where(function ($uc) use ($campusIds) {
                            $uc->whereIn('campus_id', $campusIds)
                                ->orWhereHas('campuses', fn ($c) => $c->whereIn('campuses.id', $campusIds));
                        }));
                });
        });
    }
}
