<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherApiController extends Controller
{
    /**
     * Get teacher's assigned classes with active student count.
     */
    public function classes(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $classes = ClassRoom::query()
            ->when(! $teacher->isAdmin(), function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->withCount(['students' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return response()->json([
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'room' => $c->room,
                'students_count' => $c->students_count,
            ]),
        ]);
    }

    /**
     * Get students enrolled in a specific class.
     */
    public function students(Request $request, int $classId): JsonResponse
    {
        $class = ClassRoom::findOrFail($classId);

        $students = Student::where('class_id', $class->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'gender', 'photo_path']);

        return response()->json([
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
            ],
            'students' => $students,
        ]);
    }

    /**
     * Get attendance sessions for the specified date (default today).
     */
    public function sessions(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $teacher = $request->user();

        $sessions = AttendanceSession::with('classRoom')
            ->where('session_date', $date)
            ->when(! $teacher->isAdmin(), fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orderBy('id')
            ->get();

        return response()->json([
            'date' => $date,
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'class_id' => $s->class_id,
                'class_name' => $s->classRoom?->name,
                'status' => $s->status,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'submitted_at' => $s->submitted_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Submit attendance entries for a session via tablet / mobile app.
     */
    public function markSession(Request $request, int $sessionId): JsonResponse
    {
        $session = AttendanceSession::findOrFail($sessionId);

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'exists:students,id'],
            'records.*.status' => ['required', 'string', 'in:present,absent,late'],
        ]);

        $teacher = $request->user();

        foreach ($validated['records'] as $record) {
            Attendance::updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'student_id' => $record['student_id'],
                ],
                [
                    'status' => $record['status'],
                    'marked_by' => $teacher->id,
                    'marked_at' => now(),
                    'final_status' => $record['status'],
                    'finalized_by' => $teacher->id,
                    'finalized_at' => now(),
                ]
            );
        }

        $session->update([
            'status' => AttendanceSession::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        AuditService::log('api.session.submitted', user: $teacher, details: [
            'session_id' => $session->id,
            'marked_count' => count($validated['records']),
            'submitted_by' => $teacher->id,
        ]);

        return response()->json([
            'message' => 'Attendance session submitted successfully.',
            'session_id' => $session->id,
            'status' => $session->status,
            'records_count' => count($validated['records']),
        ]);
    }
}
