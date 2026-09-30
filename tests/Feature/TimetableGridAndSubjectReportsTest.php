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

class TimetableGridAndSubjectReportsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private User $superAdmin;

    private User $teacher1;

    private User $teacher2;

    private ClassRoom $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::create([
            'name_en' => 'Tuol Kork Campus',
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
            'name' => 'Global Super Admin',
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

    public function test_teacher_can_view_weekly_schedule_grid(): void
    {
        ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => 1, // Monday
            'period_number' => 1,
            'subject' => 'Mathematics',
            'start_time' => '07:30',
            'end_time' => '08:20',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->teacher1)->get(route('teacher.schedule.index'));
        $response->assertOk();
        $response->assertSee('Weekly Teaching Timetable');
        $response->assertSee('Monday');
        $response->assertSee('Mathematics');
        $response->assertSee('Grade 10A');
        $response->assertSee('#1');
    }

    public function test_teacher_schedule_displays_substitutions_covering_and_covered(): void
    {
        $todayIso = (int) now()->dayOfWeekIso;

        $sched1 = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => $todayIso,
            'period_number' => 1,
            'subject' => 'Khmer Literature',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        // Teacher 2 covers for Teacher 1
        ScheduleSubstitution::create([
            'class_schedule_id' => $sched1->id,
            'session_date' => now()->toDateString(),
            'original_teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'created_by' => $this->admin->id,
        ]);

        // Teacher 2 visits schedule -> sees "Covering for Teacher Meas"
        $res2 = $this->actingAs($this->teacher2)->get(route('teacher.schedule.index'));
        $res2->assertOk();
        $res2->assertSee('Khmer Literature');
        $res2->assertSee('Covering for');
        $res2->assertSee('Teacher Meas');

        // Teacher 1 visits schedule -> sees "Covered by Teacher Sok"
        $res1 = $this->actingAs($this->teacher1)->get(route('teacher.schedule.index'));
        $res1->assertOk();
        $res1->assertSee('Khmer Literature');
        $res1->assertSee('Covered by');
        $res1->assertSee('Teacher Sok');
    }

    public function test_admin_and_super_admin_can_view_any_teacher_schedule(): void
    {
        ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => 2, // Tuesday
            'period_number' => 2,
            'subject' => 'Physics',
            'teacher_id' => $this->teacher2->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('teacher.schedule.index', ['teacher_id' => $this->teacher2->id])
        );
        $response->assertOk();
        $response->assertSee('Teacher Sok');
        $response->assertSee('Physics');
    }

    public function test_admin_campus_isolation_enforced_on_teacher_schedule(): void
    {
        $campus2 = Campus::create([
            'name_en' => 'Chroy Changvar Campus',
            'name_kh' => 'ជ្រោយចង្វារ',
            'code' => 'CC',
            'is_active' => true,
        ]);

        $otherTeacher = User::create([
            'name' => 'Teacher CC',
            'email' => 'cc_teacher@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $campus2->id,
            'is_active' => true,
        ]);

        // Admin of Campus 1 cannot inspect Teacher from Campus 2
        $response = $this->actingAs($this->admin)->get(
            route('teacher.schedule.index', ['teacher_id' => $otherTeacher->id])
        );
        $response->assertStatus(403);

        // Super Admin can inspect Teacher from any campus
        $superResponse = $this->actingAs($this->superAdmin)->get(
            route('teacher.schedule.index', ['teacher_id' => $otherTeacher->id])
        );
        $superResponse->assertOk();
    }

    public function test_admin_can_view_class_weekly_timetable_grid(): void
    {
        ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => 3, // Wednesday
            'period_number' => 1,
            'subject' => 'Biology',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.classes.show', $this->class));
        $response->assertOk();
        $response->assertSee('timetableGridView');
        $response->assertSee('Wednesday');
        $response->assertSee('Biology');
        $response->assertSee('Teacher Meas');
    }

    public function test_admin_can_filter_reports_by_subject_and_period(): void
    {
        $schedMath = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'Mathematics',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $schedEnglish = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 2,
            'subject' => 'English',
            'teacher_id' => $this->teacher2->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        // Math session
        $mathSession = AttendanceSession::create([
            'class_id' => $this->class->id,
            'class_schedule_id' => $schedMath->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => now()->toDateString(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        Attendance::create([
            'attendance_session_id' => $mathSession->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_PRESENT,
            'final_status' => Attendance::FINAL_PRESENT,
            'case_status' => Attendance::CASE_CLOSED,
        ]);

        // English session
        $engSession = AttendanceSession::create([
            'class_id' => $this->class->id,
            'class_schedule_id' => $schedEnglish->id,
            'teacher_id' => $this->teacher2->id,
            'session_date' => now()->toDateString(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        Attendance::create([
            'attendance_session_id' => $engSession->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_ABSENT,
            'final_status' => Attendance::FINAL_ABSENT_WITHOUT_PERMISSION,
            'case_status' => Attendance::CASE_CLOSED,
        ]);

        // Filter by Mathematics
        $mathResponse = $this->actingAs($this->admin)->get(
            route('admin.reports.index', ['subject' => 'Mathematics'])
        );
        $mathResponse->assertOk();
        $mathResponse->assertSee('Mathematics');
        $mathResponse->assertDontSee('#2 English');

        // Filter by Period 2
        $period2Response = $this->actingAs($this->admin)->get(
            route('admin.reports.index', ['period_number' => 2])
        );
        $period2Response->assertOk();
        $period2Response->assertSee('English');
        $period2Response->assertDontSee('#1 Mathematics');
    }

    public function test_reports_displays_subject_attendance_breakdown(): void
    {
        $sched = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'Chemistry',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'class_schedule_id' => $sched->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => now()->toDateString(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_PRESENT,
            'final_status' => Attendance::FINAL_PRESENT,
            'case_status' => Attendance::CASE_CLOSED,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));
        $response->assertOk();
        $response->assertSee('Subject & Period Attendance Breakdown');
        $response->assertSee('Chemistry');
        $response->assertSee('100%');
    }

    public function test_reports_csv_export_includes_period_and_subject(): void
    {
        $sched = ClassSchedule::create([
            'class_id' => $this->class->id,
            'day_of_week' => (int) now()->dayOfWeekIso,
            'period_number' => 1,
            'subject' => 'History',
            'teacher_id' => $this->teacher1->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'class_schedule_id' => $sched->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => now()->toDateString(),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => Attendance::STATUS_PRESENT,
            'final_status' => Attendance::FINAL_PRESENT,
            'case_status' => Attendance::CASE_CLOSED,
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.reports.export', ['subject' => 'History'])
        );

        $response->assertOk();
        $this->assertTrue($response->headers->contains('content-type', 'text/csv; charset=UTF-8'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Period', $content);
        $this->assertStringContainsString('Subject', $content);
        $this->assertStringContainsString('History', $content);
        $this->assertStringContainsString('#1', $content);
        $this->assertStringContainsString('Dara Student', $content);
    }
}
