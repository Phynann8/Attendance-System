<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassRequest;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $campusId = $request->user()->activeCampusId();
        $query = ClassRoom::with(['teacher', 'students', 'campus'])
            ->withCount('students')
            ->orderBy('name');

        if ($campusId) {
            $query->where('campus_id', $campusId);
        } elseif ($request->filled('campus_id') && ($request->user()->isSuperAdmin() || $request->user()->isAdmin())) {
            $query->where('campus_id', (int) $request->input('campus_id'));
        }

        $classes = $query->get();
        $campuses = Campus::where('is_active', true)->ordered()->get();

        return view('admin.classes.index', compact('classes', 'campuses'));
    }

    public function create()
    {
        return view('admin.classes.create', [
            'teachers' => User::where('role', User::ROLE_TEACHER)->orderBy('name')->get(),
            'campuses' => Campus::where('is_active', true)->ordered()->get(),
        ]);
    }

    public function store(StoreClassRequest $request)
    {
        $data = $request->validated();
        if (empty($data['campus_id']) && $request->user()->campus_id) {
            $data['campus_id'] = $request->user()->campus_id;
        }

        ClassRoom::create($data);

        return redirect()->route('admin.classes.index')->with('success', 'Class created.');
    }

    public function show(ClassRoom $class, Request $request)
    {
        $userCampusId = $request->user()->activeCampusId();
        if ($userCampusId && $class->campus_id && (int) $class->campus_id !== $userCampusId) {
            abort(403, 'You do not have permission to view classes from another campus.');
        }

        $class->load(['teacher', 'students.campus', 'campus']);

        $sessions = AttendanceSession::with('attendances')
            ->where('class_id', $class->id)
            ->orderByDesc('session_date')
            ->get();

        return view('admin.classes.show', compact('class', 'sessions'));
    }
}
