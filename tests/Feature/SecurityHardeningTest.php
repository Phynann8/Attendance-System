<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campusCHV;

    private Campus $campusKB;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campusCHV = Campus::create(['name_en' => 'Chbar Ampov', 'name_kh' => 'ច្បារអំពៅ', 'code' => 'CHV', 'is_active' => true]);
        $this->campusKB = Campus::create(['name_en' => 'Kbal Tnal', 'name_kh' => 'ក្បាលថ្នល់', 'code' => 'KB', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $this->superAdmin->campuses()->sync([$this->campusCHV->id, $this->campusKB->id]);
    }

    // ──────────────────────────────────────────────────────
    // Story 32: Password Policy Hardening (min:8)
    // ──────────────────────────────────────────────────────

    public function test_password_below_8_chars_is_rejected_on_create(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => User::ROLE_TEACHER],
            ['name' => 'Teacher', 'is_system' => true]
        );

        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'Short Pass',
            'email' => 'shortpass@school.test',
            'password' => 'abc1234',  // 7 characters - should fail
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'shortpass@school.test']);
    }

    public function test_password_8_chars_or_more_is_accepted_on_create(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => User::ROLE_TEACHER],
            ['name' => 'Teacher', 'is_system' => true]
        );

        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'Valid Pass',
            'email' => 'validpass@school.test',
            'password' => 'abcd1234',  // 8 characters - should pass
            'role_id' => $role->id,
            'campus_ids' => [$this->campusCHV->id],
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'validpass@school.test']);
    }

    public function test_password_below_8_chars_is_rejected_on_update(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => User::ROLE_TEACHER],
            ['name' => 'Teacher', 'is_system' => true]
        );

        $user = User::create([
            'name' => 'Edit Target',
            'email' => 'edittarget@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->put(route('super-admin.users.update', $user), [
            'name' => 'Edit Target',
            'email' => 'edittarget@school.test',
            'password' => 'short',  // 5 characters - should fail
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ──────────────────────────────────────────────────────
    // Story 31: Campus-Scoped Audit Log Viewer
    // ──────────────────────────────────────────────────────

    public function test_campus_scoped_admin_only_sees_audit_logs_for_their_campus(): void
    {
        // Create campus-scoped admin for CHV only
        $chvAdmin = User::create([
            'name' => 'CHV Admin',
            'email' => 'chvadmin@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_ADMIN,
            'campus_id' => $this->campusCHV->id,
            'is_active' => true,
        ]);
        $chvAdmin->campuses()->sync([$this->campusCHV->id]);

        // Create classes and attendance infrastructure
        $chvClass = ClassRoom::create(['name' => 'CHV Class', 'campus_id' => $this->campusCHV->id]);
        $kbClass = ClassRoom::create(['name' => 'KB Class', 'campus_id' => $this->campusKB->id]);

        // Create students in each campus
        $chvStudent = Student::create(['name' => 'CHV Student', 'campus_id' => $this->campusCHV->id, 'class_id' => $chvClass->id]);
        $kbStudent = Student::create(['name' => 'KB Student', 'campus_id' => $this->campusKB->id, 'class_id' => $kbClass->id]);

        $chvSession = AttendanceSession::create([
            'class_id' => $chvClass->id,
            'session_date' => now()->toDateString(),
            'status' => 'submitted',
            'teacher_id' => $this->superAdmin->id,
        ]);
        $kbSession = AttendanceSession::create([
            'class_id' => $kbClass->id,
            'session_date' => now()->toDateString(),
            'status' => 'submitted',
            'teacher_id' => $this->superAdmin->id,
        ]);

        $chvAttendance = Attendance::create([
            'attendance_session_id' => $chvSession->id,
            'student_id' => $chvStudent->id,
            'status' => 'present',
        ]);
        $kbAttendance = Attendance::create([
            'attendance_session_id' => $kbSession->id,
            'student_id' => $kbStudent->id,
            'status' => 'present',
        ]);

        // Create audit logs for each campus
        $chvLog = AttendanceLog::create([
            'attendance_id' => $chvAttendance->id,
            'user_id' => $chvAdmin->id,
            'action' => 'attendance.marked',
            'details' => 'CHV campus log',
        ]);
        $kbLog = AttendanceLog::create([
            'attendance_id' => $kbAttendance->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'attendance.marked',
            'details' => 'KB campus log',
        ]);

        // CHV admin should only see CHV logs
        $response = $this->actingAs($chvAdmin)->get(route('admin.audit-logs.index'));
        $response->assertOk();
        $response->assertSee('CHV campus log');
        $response->assertDontSee('KB campus log');
    }

    public function test_super_admin_sees_all_campus_audit_logs(): void
    {
        $chvClass = ClassRoom::create(['name' => 'CHV Class 2', 'campus_id' => $this->campusCHV->id]);
        $kbClass = ClassRoom::create(['name' => 'KB Class 2', 'campus_id' => $this->campusKB->id]);

        // Create students in each campus
        $chvStudent = Student::create(['name' => 'CHV Student 2', 'campus_id' => $this->campusCHV->id, 'class_id' => $chvClass->id]);
        $kbStudent = Student::create(['name' => 'KB Student 2', 'campus_id' => $this->campusKB->id, 'class_id' => $kbClass->id]);

        $chvSession = AttendanceSession::create([
            'class_id' => $chvClass->id,
            'session_date' => now()->toDateString(),
            'status' => 'submitted',
            'teacher_id' => $this->superAdmin->id,
        ]);
        $kbSession = AttendanceSession::create([
            'class_id' => $kbClass->id,
            'session_date' => now()->subDay()->toDateString(),
            'status' => 'submitted',
            'teacher_id' => $this->superAdmin->id,
        ]);

        $chvAttendance = Attendance::create([
            'attendance_session_id' => $chvSession->id,
            'student_id' => $chvStudent->id,
            'status' => 'present',
        ]);
        $kbAttendance = Attendance::create([
            'attendance_session_id' => $kbSession->id,
            'student_id' => $kbStudent->id,
            'status' => 'present',
        ]);

        AttendanceLog::create([
            'attendance_id' => $chvAttendance->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'attendance.marked',
            'details' => 'CHV log for super',
        ]);
        AttendanceLog::create([
            'attendance_id' => $kbAttendance->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'attendance.marked',
            'details' => 'KB log for super',
        ]);

        // Super admin should see both
        $response = $this->actingAs($this->superAdmin)->get(route('admin.audit-logs.index'));
        $response->assertOk();
        $response->assertSee('CHV log for super');
        $response->assertSee('KB log for super');
    }

    public function test_super_admin_can_filter_audit_logs_by_campus(): void
    {
        $chvClass = ClassRoom::create(['name' => 'CHV C3', 'campus_id' => $this->campusCHV->id]);
        $kbClass = ClassRoom::create(['name' => 'KB C3', 'campus_id' => $this->campusKB->id]);

        $chvStudent = Student::create(['name' => 'CHV Stu 3', 'campus_id' => $this->campusCHV->id, 'class_id' => $chvClass->id]);
        $kbStudent = Student::create(['name' => 'KB Stu 3', 'campus_id' => $this->campusKB->id, 'class_id' => $kbClass->id]);

        $chvSession = AttendanceSession::create([
            'class_id' => $chvClass->id,
            'session_date' => now()->toDateString(),
            'status' => 'submitted',
            'teacher_id' => $this->superAdmin->id,
        ]);
        $kbSession = AttendanceSession::create([
            'class_id' => $kbClass->id,
            'session_date' => now()->subDay()->toDateString(),
            'status' => 'submitted',
            'teacher_id' => $this->superAdmin->id,
        ]);

        $chvAttendance = Attendance::create([
            'attendance_session_id' => $chvSession->id,
            'student_id' => $chvStudent->id,
            'status' => 'present',
        ]);
        $kbAttendance = Attendance::create([
            'attendance_session_id' => $kbSession->id,
            'student_id' => $kbStudent->id,
            'status' => 'present',
        ]);

        AttendanceLog::create([
            'attendance_id' => $chvAttendance->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'attendance.marked',
            'details' => 'CHV filtered log',
        ]);
        AttendanceLog::create([
            'attendance_id' => $kbAttendance->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'attendance.marked',
            'details' => 'KB filtered log',
        ]);

        // Filter by CHV campus
        $response = $this->actingAs($this->superAdmin)->get(route('admin.audit-logs.index', [
            'campus_id' => $this->campusCHV->id,
        ]));
        $response->assertOk();
        $response->assertSee('CHV filtered log');
        $response->assertDontSee('KB filtered log');
    }

    // ──────────────────────────────────────────────────────
    // Story 30: Environment Credential Cleanup
    // ──────────────────────────────────────────────────────

    public function test_scripts_contain_no_hardcoded_credentials(): void
    {
        $scriptsDir = base_path('scripts');
        if (! is_dir($scriptsDir)) {
            $this->markTestSkipped('scripts/ directory not found.');
        }

        $scripts = glob($scriptsDir.'/*.ps1');
        $token = 'ATATT3xFfGF0';
        $email = 'phynann8@gmail.com';

        foreach ($scripts as $script) {
            $content = file_get_contents($script);
            $basename = basename($script);

            $this->assertStringNotContainsString(
                $token,
                $content,
                "Script {$basename} still contains a hardcoded API token."
            );
            $this->assertStringNotContainsString(
                $email,
                $content,
                "Script {$basename} still contains a hardcoded email address."
            );
        }
    }

    // ──────────────────────────────────────────────────────
    // Story 28: Login Route Rate Limiting (throttle:5,1)
    // ──────────────────────────────────────────────────────

    public function test_login_route_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), [
                'email' => 'wrong@school.test',
                'password' => 'invalid-password',
            ]);
        }

        // 6th attempt should be throttled (HTTP 429)
        $response = $this->post(route('login.attempt'), [
            'email' => 'wrong@school.test',
            'password' => 'invalid-password',
        ]);

        $response->assertStatus(429);
    }
}
