<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private Role $superAdminRole;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => User::ROLE_SUPER_ADMIN,
            'is_system' => true,
        ]);

        $this->adminRole = Role::create([
            'name' => 'Administrator',
            'slug' => User::ROLE_ADMIN,
            'is_system' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@school.test',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Standard Admin',
            'email' => 'admin@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        SchoolSetting::set('school_name', 'Default Academy');
        SchoolSetting::set('academic_year', '2025-2026');
        SchoolSetting::set('academic_term', 'Term 1');
    }

    public function test_super_admin_can_view_school_settings_page(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.settings.index'));

        $response->assertStatus(200);
        $response->assertSee('School Profile & Settings');
        $response->assertSee('Default Academy');
    }

    public function test_non_super_admin_cannot_view_or_update_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('super-admin.settings.index'));

        $response->assertStatus(403);

        $updateResponse = $this->actingAs($this->admin)
            ->put(route('super-admin.settings.update'), [
                'settings' => [
                    'school_name' => 'Hacked Academy',
                ],
            ]);

        $updateResponse->assertStatus(403);
        $this->assertEquals('Default Academy', SchoolSetting::get('school_name'));
    }

    public function test_super_admin_can_update_settings_and_cache_is_updated(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->from(route('super-admin.settings.index'))
            ->put(route('super-admin.settings.update'), [
                'settings' => [
                    'school_name' => 'Lincoln High School',
                    'academic_year' => '2026-2027',
                    'academic_term' => 'Fall Semester',
                    'school_phone' => '+1 555 123 4567',
                    'school_email' => 'contact@lincolnhigh.edu',
                    'class_start_time' => '08:30',
                    'late_grace_period_minutes' => '10',
                ],
            ]);

        $response->assertRedirect(route('super-admin.settings.index'));
        $response->assertSessionHas('success');

        // Assert database and model get reflects changes
        $this->assertEquals('Lincoln High School', SchoolSetting::get('school_name'));
        $this->assertEquals('2026-2027', SchoolSetting::get('academic_year'));
        $this->assertEquals('Fall Semester', SchoolSetting::get('academic_term'));
        $this->assertEquals('08:30', SchoolSetting::get('class_start_time'));
        $this->assertEquals(10, SchoolSetting::getInt('late_grace_period_minutes'));
    }
}
