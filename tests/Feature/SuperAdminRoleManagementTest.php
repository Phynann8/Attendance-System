<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Role $superAdminRole;

    private SystemPermission $permStudents;

    private SystemPermission $permAbsence;

    private SystemPermission $permTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => User::ROLE_SUPER_ADMIN,
            'is_system' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        $this->permStudents = SystemPermission::create([
            'name' => 'Manage Students',
            'slug' => 'admin.students',
            'module' => 'Administration',
        ]);

        $this->permAbsence = SystemPermission::create([
            'name' => 'Review Absence',
            'slug' => 'admin.absence',
            'module' => 'Administration',
        ]);

        $this->permTeacher = SystemPermission::create([
            'name' => 'Take Attendance',
            'slug' => 'teacher.attendance',
            'module' => 'Teacher',
        ]);
    }

    public function test_super_admin_can_view_roles_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.roles.index'));

        $response->assertOk()
            ->assertSee('Role-Based Access Control')
            ->assertSee('Super Administrator');
    }

    public function test_super_admin_can_create_custom_role_with_permissions(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.roles.store'), [
            'name' => 'Discipline Officer',
            'slug' => 'discipline_officer',
            'description' => 'Handles student absence follow-ups',
            'permissions' => [$this->permStudents->id, $this->permAbsence->id],
        ]);

        $response->assertRedirect(route('super-admin.roles.index'));

        $this->assertDatabaseHas('roles', [
            'name' => 'Discipline Officer',
            'slug' => 'discipline_officer',
            'is_system' => false,
        ]);

        $role = Role::where('slug', 'discipline_officer')->first();
        $this->assertCount(2, $role->systemPermissions);
        $this->assertTrue($role->hasPermission('admin.students'));
        $this->assertTrue($role->hasPermission('admin.absence'));
        $this->assertFalse($role->hasPermission('teacher.attendance'));
    }

    public function test_super_admin_can_update_custom_role_permissions(): void
    {
        $role = Role::create([
            'name' => 'Custom Reviewer',
            'slug' => 'custom_reviewer',
            'is_system' => false,
        ]);
        $role->systemPermissions()->sync([$this->permStudents->id]);

        $response = $this->actingAs($this->superAdmin)->put(route('super-admin.roles.update', $role->id), [
            'name' => 'Updated Reviewer',
            'slug' => 'updated_reviewer',
            'permissions' => [$this->permAbsence->id, $this->permTeacher->id],
        ]);

        $response->assertRedirect(route('super-admin.roles.index'));

        $role->refresh();
        $this->assertSame('Updated Reviewer', $role->name);
        $this->assertCount(2, $role->systemPermissions);
        $this->assertTrue($role->hasPermission('teacher.attendance'));
        $this->assertFalse($role->hasPermission('admin.students'));
    }

    public function test_super_admin_cannot_delete_system_role(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.roles.destroy', $this->superAdminRole->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['id' => $this->superAdminRole->id]);
    }

    public function test_super_admin_cannot_delete_role_with_assigned_users(): void
    {
        $role = Role::create([
            'name' => 'Support Staff',
            'slug' => 'support_staff',
            'is_system' => false,
        ]);

        User::create([
            'name' => 'Assigned User',
            'email' => 'assigned@test.com',
            'password' => 'password',
            'role' => $role->slug,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.roles.destroy', $role->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_super_admin_can_delete_unused_custom_role(): void
    {
        $role = Role::create([
            'name' => 'Temporary Role',
            'slug' => 'temporary_role',
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.roles.destroy', $role->id));

        $response->assertRedirect(route('super-admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_custom_role_with_module_permission_can_access_allowed_module(): void
    {
        $adminPerm = SystemPermission::create([
            'name' => 'Admin Module',
            'slug' => 'module.admin',
            'module' => 'Administration',
        ]);

        $customRole = Role::create([
            'name' => 'Assistant Principal',
            'slug' => 'assistant_principal',
            'is_system' => false,
        ]);
        $customRole->systemPermissions()->sync([$adminPerm->id]);

        $user = User::create([
            'name' => 'Assistant User',
            'email' => 'assistant@test.com',
            'password' => 'password',
            'role' => $customRole->slug,
            'role_id' => $customRole->id,
            'is_active' => true,
        ]);

        // Can access admin module route because role has module.admin permission
        $response = $this->actingAs($user)->get(route('admin.students.index'));
        $response->assertOk();

        // Cannot access teacher module because role does not have teacher permission
        $teacherResponse = $this->actingAs($user)->get(route('teacher.attendance.history'));
        $teacherResponse->assertForbidden();
    }

    public function test_super_admin_can_access_any_module(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('teacher.attendance.history'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('student-affairs.review.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('parent.permissions.index'))->assertOk();
    }
}
