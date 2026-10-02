<?php

namespace Tests\Browser;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TeacherAttendanceRollCallTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_teacher_can_use_one_click_mark_all_present_and_open_confirmation_modal(): void
    {
        $campus = Campus::create([
            'name_en' => 'Chhouk Va Campus',
            'name_kh' => 'សាខា ឈូកវ៉ា',
            'code' => 'CHV',
            'is_active' => true,
        ]);

        $teacherRole = Role::create([
            'name' => 'Teacher',
            'slug' => User::ROLE_TEACHER,
            'is_system' => true,
        ]);

        $teacher = User::create([
            'name' => 'Teacher Dara',
            'email' => 'teacher.dara@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_TEACHER,
            'role_id' => $teacherRole->id,
            'campus_id' => $campus->id,
            'is_active' => true,
        ]);

        $class = ClassRoom::create([
            'campus_id' => $campus->id,
            'name' => 'Grade 10A',
            'grade' => '10',
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'campus_id' => $campus->id,
            'class_id' => $class->id,
            'name' => 'Bopha Meas',
            'student_code' => 'STU-001',
            'is_active' => true,
        ]);

        $student2 = Student::create([
            'campus_id' => $campus->id,
            'class_id' => $class->id,
            'name' => 'Chan Dara',
            'student_code' => 'STU-002',
            'is_active' => true,
        ]);

        $session = AttendanceSession::create([
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'session_date' => now()->toDateString(),
            'status' => AttendanceSession::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student1->id,
            'status' => null,
            'is_locked' => false,
        ]);

        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student2->id,
            'status' => null,
            'is_locked' => false,
        ]);

        $this->browse(function (Browser $browser) use ($teacher, $session) {
            $browser->loginAs($teacher)
                ->visit("/teacher/attendance/{$session->id}")
                ->assertSee('Attendance — Grade 10A')
                ->assertPresent('#markAllPresentBtn')
                // 1. Click "Mark All Present" button
                ->click('#markAllPresentBtn')
                ->pause(250)
                // 2. Click "Submit Attendance" to trigger modal
                ->click('#openSubmitModalBtn')
                ->pause(250)
                // 3. Verify interactive confirmation modal is displayed with counts
                ->assertVisible('#submitConfirmModal')
                ->assertSeeIn('#modalCountPresent', '2')
                ->assertSeeIn('#modalCountAbsent', '0');
        });
    }
}
