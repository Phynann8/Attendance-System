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
        $user = $request->user();
        $campusId = $user->activeCampusId();
        $assignedCampusIds = $user->assignedCampusIds();

        $query = ClassRoom::with(['teacher.campuses', 'students', 'campus'])
            ->withCount('students')
            ->orderBy('name');

        if ($campusId) {
            $query->where('campus_id', $campusId);
        } elseif ($request->filled('campus_id') && ($user->isSuperAdmin() || $user->isAdmin())) {
            $filterCampus = (int) $request->input('campus_id');
            if ($user->isSuperAdmin() || $user->hasCampusAccess($filterCampus)) {
                $query->where('campus_id', $filterCampus);
            }
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $query->whereIn('campus_id', $assignedCampusIds);
        }

        $classes = $query->get();
        $campuses = ($user->isSuperAdmin() || empty($assignedCampusIds))
            ? Campus::where('is_active', true)->ordered()->get()
            : Campus::whereIn('id', $assignedCampusIds)->where('is_active', true)->ordered()->get();

        $teachersQuery = User::where('role', User::ROLE_TEACHER)
            ->where('is_active', true)
            ->with('campuses')
            ->orderBy('name');

        if ($campusId) {
            $teachersQuery->where(function ($q) use ($campusId) {
                $q->where('campus_id', $campusId)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $campusId));
            });
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $teachersQuery->where(function ($q) use ($assignedCampusIds) {
                $q->whereIn('campus_id', $assignedCampusIds)
                    ->orWhereHas('campuses', fn ($cq) => $cq->whereIn('campuses.id', $assignedCampusIds));
            });
        }

        $teachers = $teachersQuery->get();

        return view('admin.classes.index', compact('classes', 'campuses', 'teachers'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $campusId = $user->activeCampusId();
        $assignedCampusIds = $user->assignedCampusIds();

        $campuses = ($user->isSuperAdmin() || empty($assignedCampusIds))
            ? Campus::where('is_active', true)->ordered()->get()
            : Campus::whereIn('id', $assignedCampusIds)->where('is_active', true)->ordered()->get();

        $teachersQuery = User::where('role', User::ROLE_TEACHER)
            ->where('is_active', true)
            ->with('campuses')
            ->orderBy('name');

        if ($campusId) {
            $teachersQuery->where(function ($q) use ($campusId) {
                $q->where('campus_id', $campusId)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $campusId));
            });
        } elseif (! $user->isSuperAdmin() && ! empty($assignedCampusIds)) {
            $teachersQuery->where(function ($q) use ($assignedCampusIds) {
                $q->whereIn('campus_id', $assignedCampusIds)
                    ->orWhereHas('campuses', fn ($cq) => $cq->whereIn('campuses.id', $assignedCampusIds));
            });
        }

        return view('admin.classes.create', [
            'teachers' => $teachersQuery->get(),
            'campuses' => $campuses,
        ]);
    }

    public function store(StoreClassRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        if (empty($data['campus_id'])) {
            $data['campus_id'] = $user->activeCampusId() ?? $user->campus_id;
        }

        if (! $user->isSuperAdmin() && ! empty($data['campus_id']) && ! $user->hasCampusAccess($data['campus_id'])) {
            abort(403, 'You do not have permission to create classes for another campus.');
        }

        if (! empty($data['teacher_id']) && ! empty($data['campus_id'])) {
            $teacher = User::findOrFail($data['teacher_id']);
            if (! $teacher->hasCampusAccess($data['campus_id'])) {
                return back()->withInput()->with('error', 'The selected teacher is not assigned to this campus.');
            }
        }

        ClassRoom::create($data);

        return redirect()->route('admin.classes.index')->with('success', 'Class created.');
    }

    public function show(ClassRoom $class, Request $request)
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $class->campus_id && ! $user->hasCampusAccess($class->campus_id)) {
            abort(403, 'You do not have permission to view classes from another campus.');
        }

        $class->load(['teacher.campuses', 'students.campus', 'campus', 'activeSchedules.teacher.campuses']);

        $sessions = AttendanceSession::with(['attendances', 'schedule'])
            ->where('class_id', $class->id)
            ->orderByDesc('session_date')
            ->get();

        $teachersQuery = User::where('role', User::ROLE_TEACHER)
            ->where('is_active', true)
            ->with('campuses')
            ->orderBy('name');

        if ($class->campus_id) {
            $teachersQuery->where(function ($q) use ($class) {
                $q->where('campus_id', $class->campus_id)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $class->campus_id));
            });
        }

        $teachers = $teachersQuery->get();

        return view('admin.classes.show', compact('class', 'sessions', 'teachers'));
    }

    public function assignTeacher(Request $request, ClassRoom $class)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        if (! $user->isSuperAdmin() && $class->campus_id && ! $user->hasCampusAccess($class->campus_id)) {
            abort(403, 'You do not have permission to manage classes from another campus.');
        }

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'exists:users,id'],
        ]);

        if (! empty($validated['teacher_id'])) {
            $teacher = User::findOrFail($validated['teacher_id']);
            if (! $teacher->isTeacher() || ! $teacher->is_active) {
                return back()->with('error', 'The selected user is not an active teacher.');
            }

            if ($class->campus_id && ! $teacher->hasCampusAccess($class->campus_id)) {
                return back()->with('error', 'The selected teacher is not assigned to this campus.');
            }

            $class->update(['teacher_id' => $teacher->id]);

            return back()->with('success', "Teacher {$teacher->name} has been assigned as homeroom teacher for {$class->name}.");
        }

        $class->update(['teacher_id' => null]);

        return back()->with('success', "Homeroom teacher unassigned for {$class->name}.");
    }
}
