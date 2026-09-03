<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStudentFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ClassRoom $classA;
    private ClassRoom $classB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.test', 'password' => 'password', 'role' => 'admin']);

        $this->classA = ClassRoom::create(['name' => '10A']);
        $this->classB = ClassRoom::create(['name' => '10B']);

        Student::create(['name' => 'Dara', 'class_id' => $this->classA->id, 'is_active' => true]);
        Student::create(['name' => 'Chantrea', 'class_id' => $this->classB->id, 'is_active' => true]);
    }

    public function test_admin_sees_all_students_without_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.index'));

        $response->assertOk()
            ->assertViewHas('students', function ($students) {
                return $students->total() === 2;
            })
            ->assertSee('Dara')
            ->assertSee('Chantrea');
    }

    public function test_admin_can_filter_students_by_class(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.index', ['class_id' => $this->classA->id]));

        $response->assertOk()
            ->assertViewHas('students', function ($students) {
                return $students->total() === 1
                    && $students->first()->name === 'Dara'
                    && (int) $students->first()->class_id === $this->classA->id;
            })
            ->assertSee('Dara')
            ->assertDontSee('Chantrea');
    }

    public function test_search_respects_the_class_filter(): void
    {
        // "Chantrea" exists, but not in class 10A — the class filter must win.
        $response = $this->actingAs($this->admin)->get(route('admin.students.index', [
            'class_id' => $this->classA->id,
            'q' => 'Chantrea',
        ]));

        $response->assertOk()
            ->assertViewHas('students', fn ($students) => $students->isEmpty())
            ->assertSee('No students found.');
    }

    public function test_selected_class_stays_selected_after_filtering(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.index', ['class_id' => $this->classB->id]));

        $response->assertOk()
            ->assertSee('<option value="' . $this->classB->id . '" selected', false);
    }
}
