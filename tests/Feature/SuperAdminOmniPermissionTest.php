<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminOmniPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $teacher;

    private User $parent;

    private ClassRoom $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => User::ROLE_SUPER_ADMIN,
            'is_system' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.test',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher Som',
            'email' => 'teacher@test.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $this->parent = User::create([
            'name' => 'Parent Meas',
            'email' => 'parent@test.test',
            'password' => 'password',
            'role' => User::ROLE_PARENT,
            'is_active' => true,
        ]);

        $this->class = ClassRoom::create([
            'name' => 'Grade 10A',
            'grade' => 'Grade 10',
            'teacher_id' => $this->teacher->id,
        ]);

        $this->student = Student::create([
            'name' => 'Dara Chan',
            'class_id' => $this->class->id,
            'parent_user_id' => $this->parent->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_view_all_attendance_history_sessions(): void
    {
        AttendanceService::openSession($this->class, $this->teacher);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('teacher.attendance.history'));

        $response->assertOk()
            ->assertSee($this->class->name);
    }

    public function test_super_admin_can_open_attendance_session_for_any_class(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('teacher.attendance.open', $this->class));

        $session = AttendanceSession::where('class_id', $this->class->id)->first();
        $this->assertNotNull($session);

        $response->assertRedirect(route('teacher.attendance.mark', $session));
        $this->assertDatabaseHas('attendance_sessions', [
            'class_id' => $this->class->id,
            'status' => AttendanceSession::STATUS_OPEN,
        ]);
    }

    public function test_super_admin_can_save_attendance_draft_and_submit_session(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);

        // Super Admin saves draft marks
        $saveResponse = $this->actingAs($this->superAdmin)
            ->post(route('teacher.attendance.save', $session), [
                'statuses' => [
                    $this->student->id => 'present',
                ],
            ]);

        $saveResponse->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => 'present',
        ]);

        // Super Admin submits session
        $submitResponse = $this->actingAs($this->superAdmin)
            ->post(route('teacher.attendance.submit', $session));

        $submitResponse->assertRedirect(route('teacher.attendance.mark', $session));
        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $session->id,
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);
    }

    public function test_super_admin_can_record_late_arrival_in_gate_review(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);
        AttendanceService::saveMarks($session, $this->teacher, [$this->student->id => 'absent']);
        AttendanceService::submitSession($session, $this->teacher);

        $attendance = Attendance::where('student_id', $this->student->id)->firstOrFail();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('student-affairs.review.arrived', $attendance), [
                'arrived_at' => Carbon::now()->format('Y-m-d\TH:i'),
            ]);

        $response->assertSessionHas('success');
        $attendance->refresh();
        $this->assertEquals(Attendance::CASE_CLOSED, $attendance->case_status);
        $this->assertEquals(Attendance::FINAL_LATE, $attendance->final_status);
    }

    public function test_super_admin_can_escalate_and_decide_absence(): void
    {
        $session = AttendanceService::openSession($this->class, $this->teacher);
        AttendanceService::saveMarks($session, $this->teacher, [$this->student->id => 'absent']);
        AttendanceService::submitSession($session, $this->teacher);

        $attendance = Attendance::where('student_id', $this->student->id)->firstOrFail();

        // Super Admin escalates case (Student Affairs action)
        $this->actingAs($this->superAdmin)
            ->post(route('student-affairs.review.escalate', $attendance));

        $attendance->refresh();
        $this->assertEquals(Attendance::CASE_ESCALATED, $attendance->case_status);

        // Super Admin makes final decision (Admin action)
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.absence.decide', $attendance), [
                'decision' => 'excused',
                'requested_by' => 'Family Call',
                'reason' => 'Family emergency confirmed by Super Admin',
            ]);

        $response->assertSessionHas('success');
        $attendance->refresh();
        $this->assertEquals(Attendance::FINAL_EXCUSED, $attendance->final_status);
    }

    public function test_super_admin_can_access_parent_portal_and_submit_permission(): void
    {
        $createResponse = $this->actingAs($this->superAdmin)
            ->get(route('parent.permissions.create'));

        $createResponse->assertOk()
            ->assertSee($this->student->name);

        $storeResponse = $this->actingAs($this->superAdmin)
            ->post(route('parent.permissions.store'), [
                'student_id' => $this->student->id,
                'attendance_date' => Carbon::tomorrow()->toDateString(),
                'reason' => 'Doctor appointment approved in advance',
            ]);

        $storeResponse->assertRedirect(route('parent.permissions.index'));
        $this->assertDatabaseHas('permissions', [
            'student_id' => $this->student->id,
            'reason' => 'Doctor appointment approved in advance',
            'status' => Permission::STATUS_PENDING,
        ]);
    }
}
