<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\ScheduleSubstitution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodTimetableAndSubstitutionTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private User $superAdmin;

    private User $teacher1;

    private User $teacher2;

    private User $teacher3;

    private ClassRoom $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::create([
            'name_en' => 'Toul Kork Campus',
            'name_kh' => 'ទួលគោក',
            'code' => 'TK',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Campus Admin',
            'email' => 'admin_tk@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@school.test',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->teacher1 = User::create([
            'name' => 'Teacher Meas',
            'email' => 'meas@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);

        $this->teacher2 = User::create([
            'name' => 'Teacher Sok',
            'email' => 'sok@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);

        $this->teacher3 = User::create([
            'name' => 'Teacher Vanna',
            'email' => 'vanna@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);

        $this->class = ClassRoom::create([
            'name' => 'Grade 10A',
            'grade' => '10',
            'campus_id' => $this->campus->id,
            'teacher_id' => $this->teacher1->id,
        ]);

        $this->student = Student::create([
            'name' => 'Dara Student',
            'class_id' => $this->class->id,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_add_period_schedule_to_class(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.classes.schedules.store', $this->class),
            [
                'day_of_week' => 1, // Monday
                'period_number' => 1,
                'subject' => 'Mathematics',
                'start_time' => '08:00',
                'end_time' => '08:50',
                'teacher_id' => $this->teacher1->id,
                'is_primary' => '1',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'period_number' => 1,
            'subject' => 'Mathematics',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
        ]);
    }

    public function test_cannot_add_duplicate_period_on_same_day(): void
    {
        ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'period_number' => 1,
            'subject' => 'Mathematics',
            'start_time' => '08:00',
            'end_time' => '08:50',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.classes.schedules.store', $this->class),
            [
                'day_of_week' => 1,
                'period_number' => 1,
                'subject' => 'Physics',
                'start_time' => '08:00',
                'end_time' => '08:50',
                'teacher_id' => $this->teacher2->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(1, ClassSchedule::where('class_id', $this->class->id)->count());
    }

    public function test_admin_can_delete_period_schedule(): void
    {
        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => 2,
            'period_number' => 2,
            'subject' => 'Chemistry',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.classes.schedules.destroy', [$this->class, $schedule])
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('class_schedules', ['id' => $schedule->id]);
    }

    public function test_admin_can_assign_substitute_teacher_for_date(): void
    {
        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'Biology',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $today = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)->post(
            route('admin.substitutions.store'),
            [
                'class_schedule_id' => $schedule->id,
                'session_date' => $today,
                'substitute_teacher_id' => $this->teacher2->id,
                'reason' => 'Teacher Meas has medical leave',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sub = ScheduleSubstitution::where('class_schedule_id', $schedule->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame($this->teacher1->id, $sub->original_teacher_id);
        $this->assertSame($this->teacher2->id, $sub->substitute_teacher_id);
        $this->assertSame($today, $sub->session_date->format('Y-m-d'));
        $this->assertSame('Teacher Meas has medical leave', $sub->reason);

        // Helper check on ClassSchedule
        $activeTeacher = $schedule->getActiveTeacherForDate($today);
        $this->assertSame($this->teacher2->id, $activeTeacher->id);
    }

    public function test_cannot_assign_same_teacher_as_substitute(): void
    {
        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'Biology',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.substitutions.store'),
            [
                'class_schedule_id' => $schedule->id,
                'session_date' => now()->format('Y-m-d'),
                'substitute_teacher_id' => $this->teacher1->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, ScheduleSubstitution::count());
    }

    public function test_admin_cannot_assign_substitute_from_different_campus(): void
    {
        $campus2 = Campus::create([
            'name_en' => 'Sensok Campus',
            'name_kh' => 'សែនសុខ',
            'code' => 'SS',
            'is_active' => true,
        ]);

        $otherCampusTeacher = User::create([
            'name' => 'Teacher Other',
            'email' => 'other@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $campus2->id,
            'is_active' => true,
        ]);

        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'Biology',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.substitutions.store'),
            [
                'class_schedule_id' => $schedule->id,
                'session_date' => now()->format('Y-m-d'),
                'substitute_teacher_id' => $otherCampusTeacher->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, ScheduleSubstitution::count());
    }

    public function test_admin_can_cancel_substitution(): void
    {
        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'Biology',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $sub = ScheduleSubstitution::create([
            'class_schedule_id' => $schedule->id,
            'session_date' => now()->toDateString(),
            'original_teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.substitutions.destroy', $sub)
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('schedule_substitutions', ['id' => $sub->id]);
    }

    public function test_teacher_dashboard_shows_scheduled_periods_and_substitutions(): void
    {
        $todayIso = (int) now()->dayOfWeekIso;

        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 1,
            'subject' => 'Khmer Literature',
            'start_time' => '07:30',
            'end_time' => '08:20',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        // Teacher 1 visits dashboard -> sees the period
        $res1 = $this->actingAs($this->teacher1)->get(route('dashboard'));
        $res1->assertOk();
        $res1->assertSee("Today's Teaching Schedule");
        $res1->assertSee('Khmer Literature');
        $res1->assertSee('Period #1');

        // Now admin assigns Teacher 2 as substitute
        ScheduleSubstitution::create([
            'class_schedule_id' => $schedule->id,
            'session_date' => now()->toDateString(),
            'original_teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'created_by' => $this->admin->id,
        ]);

        // Teacher 2 visits dashboard -> sees the substituted period and "Covering for Teacher Meas"
        $res2 = $this->actingAs($this->teacher2)->get(route('dashboard'));
        $res2->assertOk();
        $res2->assertSee('Khmer Literature');
        $res2->assertSee('Covering for');
        $res2->assertSee('Teacher Meas');

        // Teacher 1 visits dashboard -> sees "Covered by Teacher Sok"
        $res3 = $this->actingAs($this->teacher1)->get(route('dashboard'));
        $res3->assertOk();
        $res3->assertSee('Covered by');
        $res3->assertSee('Teacher Sok');
    }

    public function test_substitute_teacher_can_open_and_mark_attendance(): void
    {
        $todayIso = (int) now()->dayOfWeekIso;

        $schedule = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 1,
            'subject' => 'English',
            'start_time' => '08:30',
            'end_time' => '09:20',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        ScheduleSubstitution::create([
            'class_schedule_id' => $schedule->id,
            'session_date' => now()->toDateString(),
            'original_teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'created_by' => $this->admin->id,
        ]);

        // Teacher 3 (unrelated) cannot open attendance for this period
        $unauthRes = $this->actingAs($this->teacher3)->post(
            route('teacher.attendance.open', $this->class),
            ['schedule_id' => $schedule->id]
        );
        $unauthRes->assertStatus(403);

        // Teacher 2 (substitute) can open attendance
        $openRes = $this->actingAs($this->teacher2)->post(
            route('teacher.attendance.open', $this->class),
            ['schedule_id' => $schedule->id]
        );
        $openRes->assertRedirect();

        $session = AttendanceSession::where('class_schedule_id', $schedule->id)
            ->whereDate('session_date', now()->toDateString())
            ->first();

        $this->assertNotNull($session);
        $this->assertSame($this->teacher2->id, $session->teacher_id);
        $this->assertSame(AttendanceSession::STATUS_OPEN, $session->status);

        // Substitute teacher can view mark page
        $markViewRes = $this->actingAs($this->teacher2)->get(route('teacher.attendance.mark', $session));
        $markViewRes->assertOk();
        $markViewRes->assertSee('English');

        // Substitute teacher marks student as present and submits
        $saveRes = $this->actingAs($this->teacher2)->post(
            route('teacher.attendance.save', $session),
            ['statuses' => [$this->student->id => Attendance::STATUS_PRESENT]]
        );
        $saveRes->assertRedirect();

        $submitRes = $this->actingAs($this->teacher2)->post(route('teacher.attendance.submit', $session));
        $submitRes->assertRedirect();

        $this->assertSame(AttendanceSession::STATUS_SUBMITTED, $session->fresh()->status);
        $this->assertSame(Attendance::FINAL_PRESENT, $session->attendances()->first()->final_status);
    }

    public function test_multiple_period_sessions_can_be_opened_on_same_day(): void
    {
        $todayIso = (int) now()->dayOfWeekIso;

        $period1 = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 1,
            'subject' => 'Mathematics',
            'start_time' => '08:00',
            'end_time' => '08:50',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $period2 = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 2,
            'subject' => 'Physics',
            'start_time' => '09:00',
            'end_time' => '09:50',
            'teacher_id' => $this->teacher2->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        // Teacher 1 opens Period 1
        $res1 = $this->actingAs($this->teacher1)->post(
            route('teacher.attendance.open', $this->class),
            ['schedule_id' => $period1->id]
        );
        $res1->assertRedirect();

        // Teacher 2 opens Period 2 on the exact same day for the exact same class
        $res2 = $this->actingAs($this->teacher2)->post(
            route('teacher.attendance.open', $this->class),
            ['schedule_id' => $period2->id]
        );
        $res2->assertRedirect();

        // Both sessions exist without database unique key collision!
        $this->assertSame(2, AttendanceSession::where('class_id', $this->class->id)
            ->whereDate('session_date', now()->toDateString())
            ->count());

        $session1 = AttendanceSession::where('class_schedule_id', $period1->id)->first();
        $session2 = AttendanceSession::where('class_schedule_id', $period2->id)->first();

        $this->assertNotNull($session1);
        $this->assertNotNull($session2);
        $this->assertTrue($session1->isPrimary());
        $this->assertFalse($session2->isPrimary());
    }

    public function test_primary_period_absence_escalates_to_student_affairs_while_secondary_does_not(): void
    {
        $todayIso = (int) now()->dayOfWeekIso;

        $primaryPeriod = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 1,
            'subject' => 'Morning Homeroom Period',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $secondaryPeriod = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 2,
            'subject' => 'Afternoon Art Period',
            'teacher_id' => $this->teacher2->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        // Open both
        $this->actingAs($this->teacher1)->post(
            route('teacher.attendance.open', $this->class),
            ['schedule_id' => $primaryPeriod->id]
        );

        $this->actingAs($this->teacher2)->post(
            route('teacher.attendance.open', $this->class),
            ['schedule_id' => $secondaryPeriod->id]
        );

        $primarySession = AttendanceSession::where('class_schedule_id', $primaryPeriod->id)->first();
        $secondarySession = AttendanceSession::where('class_schedule_id', $secondaryPeriod->id)->first();

        // Check initial case_status
        $primaryAtt = $primarySession->attendances()->where('student_id', $this->student->id)->first();
        $secondaryAtt = $secondarySession->attendances()->where('student_id', $this->student->id)->first();

        // Primary period absences escalate to pending case for Student Affairs
        $this->assertSame(Attendance::CASE_PENDING, $primaryAtt->case_status);

        // Secondary period does not flood the morning arrival queue
        $this->assertSame(Attendance::CASE_CLOSED, $secondaryAtt->case_status);
    }
}
