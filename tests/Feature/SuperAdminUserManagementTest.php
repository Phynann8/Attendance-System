<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Role $superAdminRole;

    private Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => User::ROLE_SUPER_ADMIN,
            'is_system' => true,
        ]);

        $this->teacherRole = Role::create([
            'name' => 'Teacher',
            'slug' => User::ROLE_TEACHER,
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
    }

    public function test_super_admin_can_view_user_management_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.users.index'));

        $response->assertOk()
            ->assertSee('User Management')
            ->assertSee('Super Admin');
    }

    public function test_non_super_admin_forbidden_from_user_management(): void
    {
        $teacher = User::create([
            'name' => 'Teacher One',
            'email' => 'teacher@test.com',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->get(route('super-admin.users.index'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_create_user_and_assign_role(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'New Staff',
            'email' => 'staff@test.com',
            'password' => 'secret123',
            'role_id' => $this->teacherRole->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'New Staff',
            'email' => 'staff@test.com',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_edit_user(): void
    {
        $user = User::create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->put(route('super-admin.users.update', $user->id), [
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
            'role_id' => $this->teacherRole->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
        ]);
    }

    public function test_super_admin_can_toggle_user_active_status(): void
    {
        $user = User::create([
            'name' => 'Active User',
            'email' => 'active@test.com',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.toggle-status', $user->id));

        $response->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.toggle-status', $user->id));

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@test.com',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => false,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'inactive@test.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_super_admin_can_soft_delete_and_restore_user(): void
    {
        $user = User::create([
            'name' => 'To Delete',
            'email' => 'delete@test.com',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.destroy', $user->id));

        $response->assertRedirect(route('super-admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertFalse($user->fresh()->is_active);

        // Restore
        $restoreResponse = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.restore', $user->id));

        $restoreResponse->assertRedirect(route('super-admin.users.index'));
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.destroy', $this->superAdmin->id));

        $response->assertRedirect();
        $this->assertNotSoftDeleted('users', ['id' => $this->superAdmin->id]);
    }

    public function test_super_admin_cannot_deactivate_themselves(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.toggle-status', $this->superAdmin->id));

        $response->assertRedirect();
        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_super_admin_can_assign_campus_to_user_and_filter_by_campus(): void
    {
        $campus = Campus::create([
            'name_en' => 'Chhouk Va',
            'name_kh' => 'ឈូកវ៉ា',
            'code' => 'CHV',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'Campus Admin',
            'email' => 'campusadmin@test.com',
            'password' => 'secret123',
            'role_id' => $this->teacherRole->id,
            'campus_id' => $campus->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('super-admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'campusadmin@test.com',
            'campus_id' => $campus->id,
        ]);

        // Filter by campus
        $filterResponse = $this->actingAs($this->superAdmin)->get(route('super-admin.users.index', [
            'campus_id' => $campus->id,
        ]));

        $filterResponse->assertOk()
            ->assertSee('Campus Admin')
            ->assertSee('CHV');
    }
}
