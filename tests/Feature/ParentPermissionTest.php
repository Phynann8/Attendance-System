<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Parent "Request Permission" flow:
 * create page lists only the parent's linked children, a valid submission is
 * stored as Pending, and a parent cannot submit on behalf of an unlinked child.
 */
class ParentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $parent;
    private User $otherParent;
    private ClassRoom $class;
    private Student $myChild;
    private Student $mySecondChild;
    private Student $otherChild;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.test', 'password' => 'password', 'role' => 'admin']);
        $this->parent = User::create(['name' => 'Sok Dara', 'email' => 'sok@test.test', 'password' => 'password', 'role' => 'parent']);
        $this->otherParent = User::create(['name' => 'Kim Ratha', 'email' => 'kim@test.test', 'password' => 'password', 'role' => 'parent']);

        $this->class = ClassRoom::create(['name' => '10A']);

        $this->myChild = Student::create(['name' => 'Dara', 'class_id' => $this->class->id, 'parent_user_id' => $this->parent->id, 'is_active' => true]);
        $this->mySecondChild = Student::create(['name' => 'Sokha', 'class_id' => $this->class->id, 'parent_user_id' => $this->parent->id, 'is_active' => true]);
        $this->otherChild = Student::create(['name' => 'Ratha', 'class_id' => $this->class->id, 'parent_user_id' => $this->otherParent->id, 'is_active' => true]);
    }

    private function futureDate(): string
    {
        return Carbon::today()->addDay()->toDateString();
    }

    public function test_create_page_lists_only_linked_children(): void
    {
        $response = $this->actingAs($this->parent)->get(route('parent.permissions.create'));

        $response->assertOk()
            ->assertSee('Dara')
            ->assertSee('Sokha')
            ->assertDontSee('Ratha');
    }

    public function test_parent_can_submit_permission_request_as_pending(): void
    {
        // NOTE: no `requested_by` field is sent, matching the parent form.
        $response = $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->myChild->id,
            'attendance_date' => $this->futureDate(),
            'reason' => 'Medical appointment',
        ]);

        $response->assertRedirect(route('parent.permissions.index'))
            ->assertSessionHas('success');

        $permission = Permission::where('student_id', $this->myChild->id)->first();
        $this->assertNotNull($permission);
        $this->assertSame(Permission::STATUS_PENDING, $permission->status);
        $this->assertSame($this->parent->name, $permission->requested_by);
        $this->assertSame(Permission::REQUESTED_BY_PARENT, $permission->requested_by_type);
    }

    public function test_parent_cannot_submit_for_an_unlinked_student(): void
    {
        $this->actingAs($this->parent)
            ->post(route('parent.permissions.store'), [
                'student_id' => $this->otherChild->id,
                'attendance_date' => $this->futureDate(),
                'reason' => 'Test',
            ])
            ->assertStatus(403);
    }

    public function test_parent_created_permission_shows_in_history_and_to_admin(): void
    {
        $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->mySecondChild->id,
            'attendance_date' => $this->futureDate(),
            'reason' => 'School trip',
        ]);

        // Parent sees it in their history.
        $history = $this->actingAs($this->parent)->get(route('parent.permissions.index'));
        $history->assertOk()->assertSee('School trip');

        // Admin sees the pending request.
        $adminView = $this->actingAs($this->admin)->get(route('admin.permissions.index'));
        $adminView->assertOk()->assertSee('School trip');
    }
}