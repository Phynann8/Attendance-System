<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AttendanceLog::with(['user', 'attendance.student.classRoom', 'attendance.session.classRoom', 'permission.student']);

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

        return view('admin.audit_logs.index', compact('logs', 'actionTypes'));
    }
}
