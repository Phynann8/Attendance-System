<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $teacher1;

    private User $teacher2;

    private Campus $campus;

    private ClassRoom $class;

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
            'teacher_id' => null,
        ]);

        Student::create([
            'name' => 'Student Ly',
            'class_id' => $this->class->id,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_and_super_admin_can_view_classes_with_teachers(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.classes.index'));
        $response->assertOk();
        $response->assertViewHas('teachers');
        $response->assertSee('Grade 10A');
        $response->assertSee('Teacher Meas');

        $showResponse = $this->actingAs($this->admin)->get(route('admin.classes.show', $this->class));
        $showResponse->assertOk();
        $showResponse->assertViewHas('teachers');
        $showResponse->assertSee('Homeroom Teacher');
        $showResponse->assertSee('No homeroom teacher assigned');
    }

    public function test_admin_can_assign_active_teacher_to_class(): void
    {
        $this->assertNull($this->class->fresh()->teacher_id);

        $response = $this->actingAs($this->admin)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $this->teacher1->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame($this->teacher1->id, $this->class->fresh()->teacher_id);
        $this->assertSame('Teacher Meas', $this->class->fresh()->teacher->name);
    }

    public function test_admin_can_unassign_teacher_from_class(): void
    {
        $this->class->update(['teacher_id' => $this->teacher1->id]);
        $this->assertNotNull($this->class->fresh()->teacher_id);

        $response = $this->actingAs($this->admin)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => '']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull($this->class->fresh()->teacher_id);
    }

    public function test_cannot_assign_inactive_user_or_non_teacher(): void
    {
        $inactiveTeacher = User::create([
            'name' => 'Inactive Teacher',
            'email' => 'inactive@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campus->id,
            'is_active' => false,
        ]);

        $parent = User::create([
            'name' => 'Parent User',
            'email' => 'parent@school.test',
            'password' => 'password',
            'role' => User::ROLE_PARENT,
            'is_active' => true,
        ]);

        // Attempt inactive teacher
        $response1 = $this->actingAs($this->admin)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $inactiveTeacher->id]
        );
        $response1->assertRedirect();
        $response1->assertSessionHas('error');
        $this->assertNull($this->class->fresh()->teacher_id);

        // Attempt non-teacher
        $response2 = $this->actingAs($this->admin)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $parent->id]
        );
        $response2->assertRedirect();
        $response2->assertSessionHas('error');
        $this->assertNull($this->class->fresh()->teacher_id);
    }

    public function test_campus_isolation_enforced_when_assigning_teacher(): void
    {
        $campus2 = Campus::create([
            'name_en' => 'Chroy Changvar Campus',
            'name_kh' => 'ជ្រោយចង្វារ',
            'code' => 'CC',
            'is_active' => true,
        ]);

        $teacherCampus2 = User::create([
            'name' => 'Teacher CC',
            'email' => 'cc_teacher@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'campus_id' => $campus2->id,
            'is_active' => true,
        ]);

        $adminCampus2 = User::create([
            'name' => 'Admin CC',
            'email' => 'cc_admin@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'campus_id' => $campus2->id,
            'is_active' => true,
        ]);

        // Admin of Campus 2 cannot manage Campus 1 class
        $response = $this->actingAs($adminCampus2)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $teacherCampus2->id]
        );
        $response->assertStatus(403);

        // Admin of Campus 1 cannot assign teacher from Campus 2 to Campus 1 class
        $response2 = $this->actingAs($this->admin)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $teacherCampus2->id]
        );
        $response2->assertRedirect();
        $response2->assertSessionHas('error');
        $this->assertNull($this->class->fresh()->teacher_id);
    }

    public function test_assigned_teacher_sees_class_on_dashboard_and_can_open_attendance(): void
    {
        // Initially teacher1 has no classes
        $dashResponse = $this->actingAs($this->teacher1)->get(route('dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertDontSee('Grade 10A');

        // Admin assigns teacher1 to Grade 10A
        $this->actingAs($this->admin)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $this->teacher1->id]
        );

        // Teacher dashboard now displays Grade 10A
        $dashResponse2 = $this->actingAs($this->teacher1)->get(route('dashboard'));
        $dashResponse2->assertOk();
        $dashResponse2->assertSee('Grade 10A');

        // Teacher can open attendance for Grade 10A
        $openResponse = $this->actingAs($this->teacher1)->post(route('teacher.attendance.open', $this->class));
        $openResponse->assertRedirect();

        // Verify session was created
        $this->assertDatabaseHas('attendance_sessions', [
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher1->id,
            'status' => 'open',
        ]);
    }

    public function test_unauthorized_user_cannot_assign_teacher(): void
    {
        $response = $this->actingAs($this->teacher1)->post(
            route('admin.classes.assign-teacher', $this->class),
            ['teacher_id' => $this->teacher2->id]
        );

        $response->assertStatus(403);
    }
}
