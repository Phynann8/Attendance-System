<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassRequest;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index()
    {
        $classes = ClassRoom::with(['teacher', 'students'])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('admin.classes.index', compact('classes'));
    }

    public function create()
    {
        return view('admin.classes.create', [
            'teachers' => User::where('role', User::ROLE_TEACHER)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreClassRequest $request)
    {
        ClassRoom::create($request->validated());

        return redirect()->route('admin.classes.index')->with('success', 'Class created.');
    }

    public function show(ClassRoom $class)
    {
        $class->load(['teacher', 'students']);

        $sessions = AttendanceSession::with('attendances')
            ->where('class_id', $class->id)
            ->orderByDesc('session_date')
            ->get();

        return view('admin.classes.show', compact('class', 'sessions'));
    }
}