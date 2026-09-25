<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $affairs;

    private ClassRoom $class;

    private Student $permissionStudent;

    private Student $normalStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.test', 'password' => 'password', 'role' => 'admin']);
        $this->teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@test.test', 'password' => 'password', 'role' => 'teacher']);
        $this->affairs = User::create(['name' => 'Affairs', 'email' => 'affairs@test.test', 'password' => 'password', 'role' => 'student_affairs']);

        $this->class = ClassRoom::create(['name' => '10A', 'teacher_id' => $this->teacher->id]);

        $this->permissionStudent = Student::create(['name' => 'Sokha', 'class_id' => $this->class->id, 'is_active' => true]);
        $this->normalStudent = Student::create(['name' => 'Dara', 'class_id' => $this->class->id, 'is_active' => true]);
    }

    private function createApprovedPermissionForToday(Student $student): Permission
    {
        return Permission::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'requested_by' => 'Parent',
            'requested_by_type' => 'parent',
            'reason' => 'Medical appointment',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
    }

    public function test_rule_9_approved_permission_is_locked_when_session_opens(): void
    {
        $this->createApprovedPermissionForToday($this->permissionStudent);

        $session = AttendanceService::openSession($this->class, $this->teacher);

        $locked = $session->attendances()->where('student_id', $this->permissionStudent->id)->first();
        $normal = $session->attendances()->where('student_id', $this->normalStudent->id)->first();

        $this->assertTrue($locked->is_locked);
        $this->assertSame(Attendance::STATUS_PERMISSION, $locked->status);
        $this->assertSame('Approved parent permission', $locked->lock_reason);
        $this->assertFalse($normal->is_locked);
        $this->assertNull($normal->status);
    }

    public function test_rules_1_2_10_teacher_cannot_modify_locked_permission_row(): void
    {
        $this->createApprovedPermissionForToday($this->permissionStudent);
        $session = AttendanceService::openSession($this->class, $this->teacher);

        // Even if a malicious request sends 'absent' for the locked student,
        // the row must stay 'permission' and locked (Rule 10).
        AttendanceService::saveMarks($session, $this->teacher, [
            $this->permissionStudent->id => 'absent',
            $this->normalStudent->id => 'present',
        ]);

        $locked = $session->attendances()->where('student_id', $this->permissionStudent->id)->first();
        $this->assertTrue($locked->is_locked);
        $this->assertSame(Attendance::STATUS_PERMISSION, $locked->status);
    }

    public function test_rules_1_2_teacher_can_only_mark_present_or_absent(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);

        $this->expectException(RuntimeException::class);
        AttendanceService::saveMarks($session, $this->teacher, [
            $this->normalStudent->id => 'late', // not allowed
        ]);
    }

    public function test_teacher_marks_present_and_absent_then_submits(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);

        AttendanceService::saveMarks($session, $this->teacher, [
            $this->permissionStudent->id => 'present',
            $this->normalStudent->id => 'present',
        ]);
        AttendanceService::submitSession($session, $this->teacher);

        $session->refresh();
        $this->assertSame(AttendanceSession::STATUS_SUBMITTED, $session->status);
        $this->assertNotNull($session->submitted_at);

        $record = $session->attendances()->where('student_id', $this->normalStudent->id)->first();
        $this->assertSame(Attendance::STATUS_PRESENT, $record->status);
        // present is final on submission
        $this->assertSame(Attendance::FINAL_PRESENT, $record->final_status);
    }

    public function test_submit_fails_when_not_all_students_are_marked(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);

        $this->expectException(RuntimeException::class);
        AttendanceService::submitSession($session, $this->teacher);
    }

    public function test_rules_3_4_student_affairs_closes_late_case(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);
        AttendanceService::saveMarks($session, $this->teacher, [
            $this->permissionStudent->id => 'present',
            $this->normalStudent->id => 'absent',
        ]);
        AttendanceService::submitSession($session, $this->teacher);

        $record = $session->attendances()->where('student_id', $this->normalStudent->id)->first();
        $submittedAt = $session->submitted_at;

        $arrived = $submittedAt->copy()->addMinutes(17);
        AttendanceService::recordLateArrival($record, $this->affairs, $arrived);

        $record->refresh();
        $this->assertSame(Attendance::FINAL_LATE, $record->final_status);
        $this->assertSame(17, $record->minutes_late);
        $this->assertSame(Attendance::CASE_CLOSED, $record->case_status);
        $this->assertSame($this->affairs->id, $record->case_closed_by);
    }

    public function test_student_affairs_escalates_no_show_to_admin(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);
        AttendanceService::saveMarks($session, $this->teacher, [
            $this->permissionStudent->id => 'present',
            $this->normalStudent->id => 'absent',
        ]);
        AttendanceService::submitSession($session, $this->teacher);

        $record = $session->attendances()->where('student_id', $this->normalStudent->id)->first();
        AttendanceService::escalateNoShow($record, $this->affairs);

        $this->assertSame(Attendance::CASE_ESCALATED, $record->fresh()->case_status);
    }

    public function test_rule_13_valid_reason_creates_permission_and_excuses_absence(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);
        AttendanceService::saveMarks($session, $this->teacher, [
            $this->permissionStudent->id => 'present',
            $this->normalStudent->id => 'absent',
        ]);
        AttendanceService::submitSession($session, $this->teacher);

        $record = $session->attendances()->where('student_id', $this->normalStudent->id)->first();
        AttendanceService::escalateNoShow($record, $this->affairs);

        $permission = AttendanceService::finalizeExcused(
            $record,
            $this->admin,
            'Parent',
            'My child is sick',
        );

        $record->refresh();
        $this->assertSame(Attendance::FINAL_EXCUSED, $record->final_status);
        $this->assertSame($permission->id, $record->permission_id);
        $this->assertSame(Permission::STATUS_APPROVED, $permission->status);
    }

    public function test_rule_14_invalid_reason_is_absent_without_permission(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);
        AttendanceService::saveMarks($session, $this->teacher, [
            $this->permissionStudent->id => 'present',
            $this->normalStudent->id => 'absent',
        ]);
        AttendanceService::submitSession($session, $this->teacher);

        $record = $session->attendances()->where('student_id', $this->normalStudent->id)->first();
        AttendanceService::finalizeAbsentWithoutPermission($record, $this->admin, 'Invalid reason');

        $this->assertSame(Attendance::FINAL_ABSENT_WITHOUT_PERMISSION, $record->fresh()->final_status);
    }

    public function test_admin_approving_pending_permission_locks_open_session_live(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);

        $pending = Permission::create([
            'student_id' => $this->normalStudent->id,
            'class_id' => $this->class->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'requested_by' => 'Parent',
            'requested_by_type' => 'parent',
            'reason' => 'Sick',
            'status' => 'pending',
        ]);

        AttendanceService::approvePermission($pending, $this->admin);

        $record = $session->attendances()->where('student_id', $this->normalStudent->id)->first();
        $this->assertTrue($record->is_locked);
        $this->assertSame(Attendance::STATUS_PERMISSION, $record->status);
    }

    public function test_audit_logs_are_written_for_actions(): void
    {
        $this->createApprovedPermissionForToday($this->permissionStudent);
        $session = AttendanceService::openSession($this->class, $this->teacher);

        AttendanceService::saveMarks($session, $this->teacher, [
            $this->normalStudent->id => 'present',
        ]);

        $this->assertDatabaseHas('attendance_logs', ['action' => 'attendance.session_opened']);
        $this->assertDatabaseHas('attendance_logs', ['action' => 'attendance.permission_locked']);
        $this->assertDatabaseHas('attendance_logs', ['action' => 'attendance.marked']);
    }
}
