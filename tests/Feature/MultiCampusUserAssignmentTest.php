<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCampusUserAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Campus $campusKB;

    private Campus $campusCHV;

    private Campus $campusTK;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campusKB = Campus::create(['name_en' => 'Kbal Tnal Campus', 'name_kh' => 'ក្បាលថ្នល់', 'code' => 'KB', 'is_active' => true]);
        $this->campusCHV = Campus::create(['name_en' => 'Chbar Ampov Campus', 'name_kh' => 'ច្បារអំពៅ', 'code' => 'CHV', 'is_active' => true]);
        $this->campusTK = Campus::create(['name_en' => 'Toul Kork Campus', 'name_kh' => 'ទួលគោក', 'code' => 'TK', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_assign_user_to_multiple_campuses_with_checkboxes(): void
    {
        $teacherRole = Role::firstOrCreate(
            ['slug' => User::ROLE_TEACHER],
            ['name' => 'Teacher', 'is_system' => true]
        );

        // 1. Create a user assigned to 2 campuses: KB and CHV
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
            'name' => 'Multi Campus Teacher',
            'email' => 'multiteacher@school.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $teacherRole->id,
            'campus_ids' => [$this->campusKB->id, $this->campusCHV->id],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('super-admin.users.index'));
        $createdUser = User::where('email', 'multiteacher@school.test')->firstOrFail();

        $this->assertTrue($createdUser->hasCampusAccess($this->campusKB->id));
        $this->assertTrue($createdUser->hasCampusAccess($this->campusCHV->id));
        $this->assertFalse($createdUser->hasCampusAccess($this->campusTK->id));
        $this->assertCount(2, $createdUser->campuses);

        // 2. Edit user and expand access to 3 campuses: KB, CHV, and TK
        $updateResponse = $this->actingAs($this->superAdmin)->put(route('super-admin.users.update', $createdUser), [
            'name' => 'Multi Campus Teacher Updated',
            'email' => 'multiteacher@school.test',
            'role_id' => $teacherRole->id,
            'campus_ids' => [$this->campusKB->id, $this->campusCHV->id, $this->campusTK->id],
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('super-admin.users.index'));
        $createdUser->refresh();

        $this->assertTrue($createdUser->hasCampusAccess($this->campusTK->id));
        $this->assertCount(3, $createdUser->campuses);
    }

    public function test_multi_campus_user_can_switch_among_assigned_campuses_but_not_unauthorized_campus(): void
    {
        $teacher = User::create([
            'name' => 'Teacher KB CHV',
            'email' => 'teacher_kb_chv@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);
        $teacher->campuses()->sync([$this->campusKB->id, $this->campusCHV->id]);

        // Switch to authorized campus KB
        $resKB = $this->actingAs($teacher)->post(route('campus.switch'), [
            'campus_id' => $this->campusKB->id,
        ]);
        $resKB->assertSessionHas('active_campus_id', $this->campusKB->id);

        // Switch to authorized campus CHV
        $resCHV = $this->actingAs($teacher)->post(route('campus.switch'), [
            'campus_id' => $this->campusCHV->id,
        ]);
        $resCHV->assertSessionHas('active_campus_id', $this->campusCHV->id);

        // Switch to unauthorized campus TK -> must return 403 Forbidden
        $resTK = $this->actingAs($teacher)->post(route('campus.switch'), [
            'campus_id' => $this->campusTK->id,
        ]);
        $resTK->assertForbidden();
    }

    public function test_class_can_only_be_assigned_to_teacher_at_that_campus(): void
    {
        // Class at CHV campus
        $chvClass = ClassRoom::create([
            'name' => 'CHV Grade 10A',
            'campus_id' => $this->campusCHV->id,
            'grade' => 'Grade 10',
        ]);

        // Teacher only at KB campus
        $kbTeacher = User::create([
            'name' => 'Teacher Only KB',
            'email' => 'kbteacher@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campusKB->id,
            'is_active' => true,
        ]);
        $kbTeacher->campuses()->sync([$this->campusKB->id]);

        // Teacher assigned to both KB and CHV campuses
        $multiTeacher = User::create([
            'name' => 'Teacher KB and CHV',
            'email' => 'multiteacher2@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campusKB->id,
            'is_active' => true,
        ]);
        $multiTeacher->campuses()->sync([$this->campusKB->id, $this->campusCHV->id]);

        // 1. Attempt to assign KB teacher to CHV class -> MUST fail and return error
        $failedAssign = $this->actingAs($this->superAdmin)->post(route('admin.classes.assign-teacher', $chvClass), [
            'teacher_id' => $kbTeacher->id,
        ]);
        $failedAssign->assertSessionHas('error', 'The selected teacher is not assigned to this campus.');
        $this->assertNull($chvClass->fresh()->teacher_id);

        // 2. Assign multi-campus teacher (who has CHV access) to CHV class -> MUST succeed
        $successAssign = $this->actingAs($this->superAdmin)->post(route('admin.classes.assign-teacher', $chvClass), [
            'teacher_id' => $multiTeacher->id,
        ]);
        $successAssign->assertSessionHas('success');
        $this->assertEquals($multiTeacher->id, $chvClass->fresh()->teacher_id);
    }

    public function test_non_super_admin_cannot_access_or_manage_classes_outside_their_assigned_campuses(): void
    {
        // Admin only assigned to KB
        $kbAdmin = User::create([
            'name' => 'KB Campus Admin',
            'email' => 'kbadmin@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'campus_id' => $this->campusKB->id,
            'is_active' => true,
        ]);
        $kbAdmin->campuses()->sync([$this->campusKB->id]);

        $kbClass = ClassRoom::create(['name' => 'KB Class 1', 'campus_id' => $this->campusKB->id]);
        $chvClass = ClassRoom::create(['name' => 'CHV Class 1', 'campus_id' => $this->campusCHV->id]);

        // KB Admin can view KB Class
        $this->actingAs($kbAdmin)
            ->get(route('admin.classes.show', $kbClass))
            ->assertOk();

        // KB Admin CANNOT view CHV Class -> 403 Forbidden
        $this->actingAs($kbAdmin)
            ->get(route('admin.classes.show', $chvClass))
            ->assertForbidden();

        // Super Admin CAN view CHV Class
        $this->actingAs($this->superAdmin)
            ->get(route('admin.classes.show', $chvClass))
            ->assertOk();
    }

    public function test_topbar_shows_all_campuses_by_default_and_updates_on_switch(): void
    {
        // 1. Super Admin with access to all 3 campuses defaults to All Campuses
        $response = $this->actingAs($this->superAdmin)->get(route('admin.classes.index'));
        $response->assertOk();
        $response->assertSee('All Campuses (3)');
        $response->assertSee('id="topbarCampusDropdown"', false);
        $response->assertSee('KB');
        $response->assertSee('CHV');
        $response->assertSee('TK');

        // 2. Switch to campus KB
        $switchRes = $this->actingAs($this->superAdmin)->post(route('campus.switch'), [
            'campus_id' => $this->campusKB->id,
        ]);
        $switchRes->assertSessionHas('active_campus_id', $this->campusKB->id);

        // 3. Topbar now displays (KB)
        $followRes = $this->actingAs($this->superAdmin)
            ->withSession(['active_campus_id' => $this->campusKB->id])
            ->get(route('admin.classes.index'));
        $followRes->assertOk();
        $followRes->assertSee('(KB)');
        $followRes->assertDontSee('All Campuses (3)');

        // 4. Switch back to All Campuses
        $switchAllRes = $this->actingAs($this->superAdmin)->post(route('campus.switch'), [
            'campus_id' => '',
        ]);
        $switchAllRes->assertSessionMissing('active_campus_id');

        $backRes = $this->actingAs($this->superAdmin)->get(route('admin.classes.index'));
        $backRes->assertOk();
        $backRes->assertSee('All Campuses (3)');
    }

    public function test_single_campus_user_shows_static_pill_without_dropdown(): void
    {
        $singleAdmin = User::create([
            'name' => 'Single KB Admin',
            'email' => 'single_kb@school.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'campus_id' => $this->campusKB->id,
            'is_active' => true,
        ]);
        $singleAdmin->campuses()->sync([$this->campusKB->id]);

        $response = $this->actingAs($singleAdmin)->get(route('admin.classes.index'));
        $response->assertOk();
        $response->assertSee('(KB)');
        $response->assertDontSee('id="topbarCampusDropdown"', false);
    }
}
