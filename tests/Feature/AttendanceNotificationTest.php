<?php

namespace Tests\Feature;

use App\Jobs\SendAttendanceNotificationJob;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AttendanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $parent;

    private ClassRoom $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'Teacher Chem',
            'email' => 'teacher.chem@test.test',
            'password' => 'password123',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $this->parent = User::create([
            'name' => 'Parent Rath',
            'email' => 'parent.rath@test.test',
            'password' => 'password123',
            'role' => User::ROLE_PARENT,
            'telegram_chat_id' => '987654321',
            'phone' => '+85512998877',
            'is_active' => true,
        ]);

        $this->class = ClassRoom::create([
            'name' => 'Grade 8B',
            'teacher_id' => $this->teacher->id,
        ]);

        $this->student = Student::create([
            'name' => 'Vannak Rath',
            'class_id' => $this->class->id,
            'parent_user_id' => $this->parent->id,
            'parent_name' => $this->parent->name,
            'parent_phone' => $this->parent->phone,
            'telegram_chat_id' => '987654321',
            'is_active' => true,
        ]);
    }

    public function test_submitting_session_dispatches_notification_job_for_absent_students(): void
    {
        Queue::fake([SendAttendanceNotificationJob::class]);

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => AttendanceSession::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $attendance = Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_ABSENT,
            'marked_by' => $this->teacher->id,
            'is_locked' => false,
        ]);

        AttendanceService::submitSession($session, $this->teacher);

        Queue::assertPushed(SendAttendanceNotificationJob::class, function ($job) use ($attendance) {
            return $job->attendanceId === $attendance->id;
        });
    }

    public function test_present_students_do_not_dispatch_absence_notifications(): void
    {
        Queue::fake([SendAttendanceNotificationJob::class]);

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => AttendanceSession::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_PRESENT,
            'marked_by' => $this->teacher->id,
            'is_locked' => false,
        ]);

        AttendanceService::submitSession($session, $this->teacher);

        Queue::assertNotPushed(SendAttendanceNotificationJob::class);
    }

    public function test_notification_job_delivers_telegram_message_to_parent_linked_chat(): void
    {
        config(['services.telegram.bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        $attendance = Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_ABSENT,
            'final_status' => null,
            'is_locked' => false,
        ]);

        // Run the job synchronously
        (new SendAttendanceNotificationJob($attendance->id))->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org')
                && $request['chat_id'] === '987654321'
                && str_contains($request['text'], 'Vannak Rath')
                && str_contains($request['text'], 'ABSENT');
        });

        $this->assertDatabaseHas('attendance_logs', [
            'action' => 'notification.parent_alert_sent',
            'attendance_id' => $attendance->id,
        ]);
    }

    public function test_late_arrival_dispatches_notification_with_late_status(): void
    {
        Queue::fake([SendAttendanceNotificationJob::class]);

        $affairs = User::create([
            'name' => 'Affairs Officer',
            'email' => 'affairs.officer@test.test',
            'password' => 'password123',
            'role' => User::ROLE_STUDENT_AFFAIRS,
            'is_active' => true,
        ]);

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
            'submitted_at' => now()->subHour(),
        ]);

        $attendance = Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_ABSENT,
            'case_status' => Attendance::CASE_PENDING,
            'is_locked' => false,
        ]);

        AttendanceService::recordLateArrival($attendance, $affairs, now());

        Queue::assertPushed(SendAttendanceNotificationJob::class, function ($job) use ($attendance) {
            return $job->attendanceId === $attendance->id
                && $job->overrideStatus === Attendance::FINAL_LATE;
        });
    }
}
