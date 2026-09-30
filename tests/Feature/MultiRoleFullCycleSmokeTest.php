<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRoleFullCycleSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_cycle_school_day_attendance_workflow_across_all_five_roles(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00'); // Thursday

        // =========================================================================
        // STEP 1: Super Admin Multi-Campus Setup
        // =========================================================================
        $campus = Campus::create([
            'name_en' => 'North Campus',
            'name_kh' => 'សាខា ខាងជើង',
            'code' => 'NC',
            'is_active' => true,
        ]);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $superAdmin->campuses()->sync([$campus->id]);

        $adminRole = Role::firstOrCreate(['slug' => User::ROLE_ADMIN], ['name' => 'Admin', 'is_system' => true]);
        $teacherRole = Role::firstOrCreate(['slug' => User::ROLE_TEACHER], ['name' => 'Teacher', 'is_system' => true]);
        $saRole = Role::firstOrCreate(['slug' => User::ROLE_STUDENT_AFFAIRS], ['name' => 'Student Affairs', 'is_system' => true]);

        // Super Admin provisions Campus Admin
        $resAdmin = $this->actingAs($superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'Campus Admin',
            'email' => 'admin.nc@school.test',
            'password' => 'securepass123',
            'role_id' => $adminRole->id,
            'campus_ids' => [$campus->id],
            'is_active' => true,
        ]);
        $resAdmin->assertRedirect(route('super-admin.users.index'));
        $admin = User::where('email', 'admin.nc@school.test')->firstOrFail();
        $this->assertTrue($admin->hasCampusAccess($campus->id));

        // Super Admin provisions Teacher
        $resTeacher = $this->actingAs($superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'Teacher Sarah',
            'email' => 'teacher.sarah@school.test',
            'password' => 'securepass123',
            'role_id' => $teacherRole->id,
            'campus_ids' => [$campus->id],
            'is_active' => true,
        ]);
        $resTeacher->assertRedirect(route('super-admin.users.index'));
        $teacher = User::where('email', 'teacher.sarah@school.test')->firstOrFail();

        // Super Admin provisions Student Affairs Officer
        $saOfficer = User::create([
            'name' => 'SA Officer John',
            'email' => 'sa.john@school.test',
            'password' => bcrypt('securepass123'),
            'role' => User::ROLE_STUDENT_AFFAIRS,
            'role_id' => $saRole->id,
            'campus_id' => $campus->id,
            'is_active' => true,
        ]);
        $saOfficer->campuses()->sync([$campus->id]);

        // =========================================================================
        // STEP 2: Admin Roster & Timetable Configuration
        // =========================================================================
        $class = ClassRoom::create([
            'name' => 'Grade 11A',
            'campus_id' => $campus->id,
            'grade' => 'Grade 11',
            'teacher_id' => $teacher->id,
        ]);

        $studentAlice = Student::create([
            'name' => 'Alice Walker',
            'student_code' => 'STU-NC-001',
            'class_id' => $class->id,
            'campus_id' => $campus->id,
            'parent_name' => 'Mr. Walker',
            'parent_phone' => '012345678',
        ]);

        $studentBob = Student::create([
            'name' => 'Bob Davis',
            'student_code' => 'STU-NC-002',
            'class_id' => $class->id,
            'campus_id' => $campus->id,
            'parent_name' => 'Mrs. Davis',
            'parent_phone' => '098765432',
        ]);

        // Create weekly timetable schedule: Period 1 Mathematics on Thursday (day 4)
        $schedule = ClassSchedule::create([
            'class_id' => $class->id,
            'day_of_week' => 4, // Thursday
            'period_number' => 1,
            'subject' => 'Mathematics',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'teacher_id' => $teacher->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        // =========================================================================
        // STEP 3: Teacher Roll-Call Execution & Submission
        // =========================================================================
        // Teacher opens today's session
        $resOpen = $this->actingAs($teacher)->post(route('teacher.attendance.open', $class), [
            'class_schedule_id' => $schedule->id,
            'date' => '2026-10-01',
        ]);
        $session = AttendanceSession::where('class_id', $class->id)
            ->whereDate('session_date', '2026-10-01')
            ->firstOrFail();
        $this->assertEquals(AttendanceSession::STATUS_OPEN, $session->status);

        // Teacher marks attendance: Alice = Present, Bob = Absent
        $this->actingAs($teacher)->post(route('teacher.attendance.save', $session), [
            'statuses' => [
                $studentAlice->id => Attendance::STATUS_PRESENT,
                $studentBob->id => Attendance::STATUS_ABSENT,
            ],
        ]);

        // Teacher submits session
        $resSubmit = $this->actingAs($teacher)->post(route('teacher.attendance.submit', $session));
        $resSubmit->assertRedirect();
        $session->refresh();
        $this->assertEquals(AttendanceSession::STATUS_SUBMITTED, $session->status);

        $attendanceAlice = Attendance::where('attendance_session_id', $session->id)->where('student_id', $studentAlice->id)->firstOrFail();
        $attendanceBob = Attendance::where('attendance_session_id', $session->id)->where('student_id', $studentBob->id)->firstOrFail();

        $this->assertEquals(Attendance::STATUS_PRESENT, $attendanceAlice->status);
        $this->assertEquals(Attendance::STATUS_ABSENT, $attendanceBob->status);
        $this->assertEquals(Attendance::CASE_PENDING, $attendanceBob->case_status);

        // =========================================================================
        // STEP 4: Student Affairs Unresolved Absence Resolution (Late Arrival)
        // =========================================================================
        // Student Affairs views pending review list
        $resSAReview = $this->actingAs($saOfficer)->get(route('student-affairs.review.index'));
        $resSAReview->assertOk();
        $resSAReview->assertSee('Bob Davis');

        // Bob arrives 20 minutes late with traffic excuse
        $resArrived = $this->actingAs($saOfficer)->post(route('student-affairs.review.arrived', $attendanceBob), [
            'arrived_at' => '2026-10-01T08:20',
        ]);
        $resArrived->assertSessionHasNoErrors();
        $resArrived->assertRedirect();

        $attendanceBob->refresh();
        $this->assertEquals(Attendance::FINAL_LATE, $attendanceBob->final_status);
        $this->assertEquals(Attendance::CASE_CLOSED, $attendanceBob->case_status);
        $this->assertEquals(20, $attendanceBob->minutes_late);

        // =========================================================================
        // STEP 5: Admin Attendance Reports & Audit Verification
        // =========================================================================
        // Admin views report dashboard for Grade 11A
        $resReport = $this->actingAs($admin)->get(route('admin.reports.index', [
            'class_id' => $class->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
        ]));
        $resReport->assertOk();
        $resReport->assertSee('Alice Walker');
        $resReport->assertSee('Bob Davis');

        // Admin views audit log history
        $resAudit = $this->actingAs($admin)->get(route('admin.audit-logs.index'));
        $resAudit->assertOk();
        $resAudit->assertSee('Alice Walker');

        $this->assertDatabaseHas('attendance_logs', [
            'attendance_id' => $attendanceBob->id,
            'user_id' => $saOfficer->id,
        ]);

        // =========================================================================
        // STEP 6: Parent Portal Access
        // =========================================================================
        $parent = User::create([
            'name' => 'Mrs. Davis',
            'email' => 'parent.davis@school.test',
            'phone' => '098765432',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PARENT,
            'campus_id' => $campus->id,
            'is_active' => true,
        ]);

        $resParent = $this->actingAs($parent)->get(route('parent.permissions.index'));
        $resParent->assertOk();

        // Lifecycle completed successfully!
        Carbon::setTestNow(); // Reset clock
    }
}
