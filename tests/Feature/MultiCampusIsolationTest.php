<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCampusIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus1;

    private Campus $campus2;

    private User $adminCampus1;

    private User $adminCampus2;

    private User $superAdmin;

    private ClassRoom $classCampus1;

    private ClassRoom $classCampus2;

    private Student $studentCampus1;

    private Student $studentCampus2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus1 = Campus::create([
            'id' => 1,
            'name_en' => 'Chhouk Va Campus',
            'name_kh' => 'សាខា ឈូកវ៉ា',
            'code' => 'CHV',
            'is_active' => true,
        ]);

        $this->campus2 = Campus::create([
            'id' => 2,
            'name_en' => 'Kamboul Campus',
            'name_kh' => 'សាខា កំបូល',
            'code' => 'KB',
            'is_active' => true,
        ]);

        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => User::ROLE_ADMIN,
            'is_system' => true,
        ]);

        $superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => User::ROLE_SUPER_ADMIN,
            'is_system' => true,
        ]);

        $this->adminCampus1 = User::create([
            'name' => 'Admin CHV',
            'email' => 'admin.chv@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $adminRole->id,
            'campus_id' => $this->campus1->id,
            'is_active' => true,
        ]);

        $this->adminCampus2 = User::create([
            'name' => 'Admin KB',
            'email' => 'admin.kb@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $adminRole->id,
            'campus_id' => $this->campus2->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@school.test',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'role_id' => $superAdminRole->id,
            'campus_id' => null,
            'is_active' => true,
        ]);

        $this->classCampus1 = ClassRoom::create([
            'name' => '10A - CHV',
            'campus_id' => $this->campus1->id,
        ]);

        $this->classCampus2 = ClassRoom::create([
            'name' => '10B - KB',
            'campus_id' => $this->campus2->id,
        ]);

        $this->studentCampus1 = Student::create([
            'name' => 'Student CHV',
            'student_code' => 'CHV-001',
            'campus_id' => $this->campus1->id,
            'class_id' => $this->classCampus1->id,
            'is_active' => true,
        ]);

        $this->studentCampus2 = Student::create([
            'name' => 'Student KB',
            'student_code' => 'KB-001',
            'campus_id' => $this->campus2->id,
            'class_id' => $this->classCampus2->id,
            'is_active' => true,
        ]);
    }

    public function test_campus_admin_only_sees_classes_from_own_campus(): void
    {
        $response1 = $this->actingAs($this->adminCampus1)->get(route('admin.classes.index'));
        $response1->assertOk()
            ->assertSee('10A - CHV')
            ->assertDontSee('10B - KB');

        $response2 = $this->actingAs($this->adminCampus2)->get(route('admin.classes.index'));
        $response2->assertOk()
            ->assertSee('10B - KB')
            ->assertDontSee('10A - CHV');
    }

    public function test_campus_admin_cannot_view_class_from_another_campus(): void
    {
        $response = $this->actingAs($this->adminCampus1)->get(route('admin.classes.show', $this->classCampus2));
        $response->assertForbidden();

        $ownResponse = $this->actingAs($this->adminCampus1)->get(route('admin.classes.show', $this->classCampus1));
        $ownResponse->assertOk()
            ->assertSee('10A - CHV');
    }

    public function test_campus_admin_only_sees_students_from_own_campus(): void
    {
        $response1 = $this->actingAs($this->adminCampus1)->get(route('admin.students.index'));
        $response1->assertOk()
            ->assertSee('Student CHV')
            ->assertDontSee('Student KB');

        $response2 = $this->actingAs($this->adminCampus2)->get(route('admin.students.index'));
        $response2->assertOk()
            ->assertSee('Student KB')
            ->assertDontSee('Student CHV');
    }

    public function test_campus_admin_cannot_view_student_from_another_campus(): void
    {
        $response = $this->actingAs($this->adminCampus1)->get(route('admin.students.show', $this->studentCampus2));
        $response->assertForbidden();

        $ownResponse = $this->actingAs($this->adminCampus1)->get(route('admin.students.show', $this->studentCampus1));
        $ownResponse->assertOk()
            ->assertSee('Student CHV');
    }

    public function test_campus_admin_cannot_reopen_attendance_session_from_another_campus(): void
    {
        $sessionKb = AttendanceSession::create([
            'class_id' => $this->classCampus2->id,
            'teacher_id' => $this->adminCampus2->id,
            'session_date' => today(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($this->adminCampus1)->post(route('admin.attendance-sessions.reopen', $sessionKb), [
            'reason' => 'Admin from other campus trying to reopen',
        ]);

        $response->assertForbidden();
    }

    public function test_campus_admin_cannot_view_or_decide_absence_from_another_campus(): void
    {
        $sessionKb = AttendanceSession::create([
            'class_id' => $this->classCampus2->id,
            'teacher_id' => $this->adminCampus2->id,
            'session_date' => today(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        $attendanceKb = Attendance::create([
            'attendance_session_id' => $sessionKb->id,
            'student_id' => $this->studentCampus2->id,
            'status' => Attendance::STATUS_ABSENT,
            'case_status' => Attendance::CASE_ESCALATED,
        ]);

        // Viewing absence show
        $showResponse = $this->actingAs($this->adminCampus1)->get(route('admin.absence.show', $attendanceKb));
        $showResponse->assertForbidden();

        // Submitting absence decision
        $decideResponse = $this->actingAs($this->adminCampus1)->post(route('admin.absence.decide', $attendanceKb), [
            'decision' => 'absent_without_permission',
        ]);
        $decideResponse->assertForbidden();
    }

    public function test_campus_admin_cannot_view_or_approve_permission_from_another_campus(): void
    {
        $permissionKb = Permission::create([
            'student_id' => $this->studentCampus2->id,
            'class_id' => $this->classCampus2->id,
            'attendance_date' => today(),
            'requested_by' => 'Parent KB',
            'requested_by_type' => Permission::REQUESTED_BY_PARENT,
            'reason' => 'Sick',
            'status' => Permission::STATUS_PENDING,
        ]);

        // Viewing permission show
        $showResponse = $this->actingAs($this->adminCampus1)->get(route('admin.permissions.show', $permissionKb));
        $showResponse->assertForbidden();

        // Approving permission
        $approveResponse = $this->actingAs($this->adminCampus1)->post(route('admin.permissions.approve', $permissionKb));
        $approveResponse->assertForbidden();

        // Rejecting permission
        $rejectResponse = $this->actingAs($this->adminCampus1)->post(route('admin.permissions.reject', $permissionKb));
        $rejectResponse->assertForbidden();
    }

    public function test_student_affairs_cannot_act_on_another_campus_case(): void
    {
        $studentAffairsRole = Role::create([
            'name' => 'Student Affairs',
            'slug' => User::ROLE_STUDENT_AFFAIRS,
            'is_system' => true,
        ]);

        $saUserCampus1 = User::create([
            'name' => 'SA CHV',
            'email' => 'sa.chv@school.test',
            'password' => 'password',
            'role' => User::ROLE_STUDENT_AFFAIRS,
            'role_id' => $studentAffairsRole->id,
            'campus_id' => $this->campus1->id,
            'is_active' => true,
        ]);

        $sessionKb = AttendanceSession::create([
            'class_id' => $this->classCampus2->id,
            'teacher_id' => $this->adminCampus2->id,
            'session_date' => today(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        $attendanceKb = Attendance::create([
            'attendance_session_id' => $sessionKb->id,
            'student_id' => $this->studentCampus2->id,
            'status' => Attendance::STATUS_ABSENT,
            'case_status' => Attendance::CASE_PENDING,
        ]);

        // Try marking arrived
        $arrivedResponse = $this->actingAs($saUserCampus1)->post(route('student-affairs.review.arrived', $attendanceKb), [
            'arrived_at' => today()->format('Y-m-d').'T08:30',
        ]);
        $arrivedResponse->assertForbidden();

        // Try escalating
        $escalateResponse = $this->actingAs($saUserCampus1)->post(route('student-affairs.review.escalate', $attendanceKb));
        $escalateResponse->assertForbidden();
    }

    public function test_super_admin_can_see_all_campuses_and_switch_active_campus(): void
    {
        // Without active campus filter, Super Admin sees both classes
        $response = $this->actingAs($this->superAdmin)->get(route('admin.classes.index'));
        $response->assertOk()
            ->assertSee('10A - CHV')
            ->assertSee('10B - KB');

        // Switch to Campus 1 (CHV)
        $switchResponse = $this->actingAs($this->superAdmin)->post(route('campus.switch'), [
            'campus_id' => $this->campus1->id,
        ]);
        $switchResponse->assertRedirect();
        $this->assertEquals($this->campus1->id, session('active_campus_id'));

        // Now viewing classes only shows Campus 1
        $filteredResponse = $this->actingAs($this->superAdmin)->get(route('admin.classes.index'));
        $filteredResponse->assertOk()
            ->assertSee('10A - CHV')
            ->assertDontSee('10B - KB');

        // Switch back to All Campuses
        $this->actingAs($this->superAdmin)->post(route('campus.switch'), [
            'campus_id' => '',
        ]);
        $this->assertNull(session('active_campus_id'));

        $allResponse = $this->actingAs($this->superAdmin)->get(route('admin.classes.index'));
        $allResponse->assertOk()
            ->assertSee('10A - CHV')
            ->assertSee('10B - KB');
    }

    public function test_regular_admin_cannot_access_campus_switch_endpoint(): void
    {
        $response = $this->actingAs($this->adminCampus1)->post(route('campus.switch'), [
            'campus_id' => $this->campus2->id,
        ]);

        $response->assertForbidden();
    }

    public function test_campus_admin_reports_and_export_are_scoped_to_own_campus(): void
    {
        $sessionChv = AttendanceSession::create([
            'class_id' => $this->classCampus1->id,
            'teacher_id' => $this->adminCampus1->id,
            'session_date' => today(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);
        Attendance::create([
            'attendance_session_id' => $sessionChv->id,
            'student_id' => $this->studentCampus1->id,
            'status' => Attendance::STATUS_PRESENT,
            'final_status' => Attendance::FINAL_PRESENT,
        ]);

        $sessionKb = AttendanceSession::create([
            'class_id' => $this->classCampus2->id,
            'teacher_id' => $this->adminCampus2->id,
            'session_date' => today(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);
        Attendance::create([
            'attendance_session_id' => $sessionKb->id,
            'student_id' => $this->studentCampus2->id,
            'status' => Attendance::STATUS_PRESENT,
            'final_status' => Attendance::FINAL_PRESENT,
        ]);

        // Admin 1 index view
        $reportResponse = $this->actingAs($this->adminCampus1)->get(route('admin.reports.index', ['preset' => 'today']));
        $reportResponse->assertOk()
            ->assertSee('Student CHV')
            ->assertDontSee('Student KB');

        // Admin 1 CSV export
        $exportResponse = $this->actingAs($this->adminCampus1)->get(route('admin.reports.export', ['preset' => 'today']));
        $exportResponse->assertOk();
        $csvContent = $exportResponse->streamedContent();
        $this->assertStringContainsString('Student CHV', $csvContent);
        $this->assertStringNotContainsString('Student KB', $csvContent);
    }
}
