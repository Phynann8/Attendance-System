<?php

namespace Tests\Feature;

use App\Models\Campus;
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
            ->assertSee('<option value="'.$this->classB->id.'" selected', false);
    }

    public function test_admin_can_search_by_khmer_name_and_student_code(): void
    {
        Student::create([
            'name' => 'Serey Vathana',
            'khmer_name' => 'សិរី វឌ្ឍនា',
            'student_code' => 'S-2026-999',
            'gender' => 'M',
            'class_id' => $this->classA->id,
            'is_active' => true,
        ]);

        // Search by Khmer name
        $responseKh = $this->actingAs($this->admin)->get(route('admin.students.index', ['q' => 'វឌ្ឍនា']));
        $responseKh->assertOk()
            ->assertSee('Serey Vathana')
            ->assertSee('សិរី វឌ្ឍនា')
            ->assertSee('S-2026-999');

        // Search by Student Code
        $responseCode = $this->actingAs($this->admin)->get(route('admin.students.index', ['q' => 'S-2026-999']));
        $responseCode->assertOk()
            ->assertSee('Serey Vathana')
            ->assertSee('S-2026-999');
    }

    public function test_admin_student_show_displays_khmer_name_and_code(): void
    {
        $student = Student::create([
            'name' => 'Sokha Chan',
            'khmer_name' => 'សុខា ចាន់',
            'student_code' => 'S-2026-888',
            'gender' => 'F',
            'class_id' => $this->classA->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.show', $student));
        $response->assertOk()
            ->assertSee('Sokha Chan')
            ->assertSee('សុខា ចាន់')
            ->assertSee('S-2026-888')
            ->assertSee('Female');
    }

    public function test_student_list_shows_each_value_in_its_own_column_in_the_requested_order(): void
    {
        $campus = Campus::create([
            'name_en' => 'Kamboul Campus',
            'name_kh' => 'សាខា កំបូល',
            'code' => 'KB',
            'is_active' => true,
        ]);

        Student::create([
            'campus_id' => $campus->id,
            'name' => 'Aknonny Mengkong',
            'khmer_name' => 'អនន្តនី ម៉េងគង់',
            'student_code' => '2026-44-401621',
            'gender' => 'M',
            'class_id' => $this->classA->id,
            'parent_name' => 'Meng Kong Sr',
            'parent_phone' => '012345678',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index'));

        $response->assertOk()->assertSeeInOrder([
            '<th>N.O</th>',
            '<th>Campus</th>',
            '<th>Student ID</th>',
            '<th>Student Name</th>',
            '<th>Khmer Name</th>',
            '<th>Gender</th>',
            '<th>Class</th>',
            '<th>Parent</th>',
            '<th>Phone</th>',
            '<th>Status</th>',
            '<th>Actions</th>',
        ], false);

        $html = $response->getContent();
        $rowStart = strpos($html, '<tbody>');
        $this->assertNotFalse($rowStart);

        $row = substr($html, $rowStart, strpos($html, '</tr>', $rowStart) - $rowStart);

        $positions = [];
        foreach (['KB', '2026-44-401621', 'Aknonny Mengkong', 'អនន្តនី ម៉េងគង់', 'Male', '10A', 'Meng Kong Sr', '012345678', 'Active'] as $value) {
            $position = strpos($row, $value);

            $this->assertNotFalse($position, "Expected [{$value}] inside the student row.");
            $this->assertGreaterThan(end($positions) ?: 0, $position, "Expected [{$value}] to appear after the previous column value.");

            $positions[] = $position;
        }
    }

    public function test_khmer_name_uses_the_khmer_font_hook_and_english_does_not(): void
    {
        Student::create([
            'name' => 'Aknonny Mengkong',
            'khmer_name' => 'អនន្តនី ម៉េងគង់',
            'class_id' => $this->classA->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index'));

        $response->assertOk()
            ->assertSee('<span class="font-khmer" lang="km"', false)
            ->assertSee('<td style="color: #1e293b;">Aknonny Mengkong</td>', false)
            ->assertDontSee('style="font-family: monospace;', false);
    }
}
