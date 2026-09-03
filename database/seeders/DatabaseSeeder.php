<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private User $admin;
    private User $teacher;
    private User $affairs;
    private User $parent;

    private ClassRoom $classA;

    public function run(): void
    {
        if (User::where('email', 'admin@school.test')->exists()) {
            $this->command?->info('Demo data already present — skipping seeder.');

            return;
        }

        $this->seedUsers();
        $this->seedClassesAndStudents();
        $this->seedYesterdayWorkflow();
        $this->seedTodayLiveState();

        $this->command?->info('Demo data seeded.');
    }

    private function seedUsers(): void
    {
        $this->admin = User::create([
            'name' => 'Admin Office', 'email' => 'admin@school.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        $this->teacher = User::create([
            'name' => 'Teacher Dara', 'email' => 'teacher@school.test', 'password' => 'password', 'role' => User::ROLE_TEACHER,
        ]);
        $this->affairs = User::create([
            'name' => 'Student Affairs', 'email' => 'affairs@school.test', 'password' => 'password', 'role' => User::ROLE_STUDENT_AFFAIRS,
        ]);
        $this->parent = User::create([
            'name' => 'Sok Dara', 'email' => 'parent@school.test', 'password' => 'password', 'role' => User::ROLE_PARENT,
        ]);
    }

    private function seedClassesAndStudents(): void
    {
        $this->classA = ClassRoom::create([
            'name' => '10A', 'grade' => 'Grade 10', 'teacher_id' => $this->teacher->id,
        ]);

        $classB = ClassRoom::create([
            'name' => '9B', 'grade' => 'Grade 9', 'teacher_id' => $this->teacher->id,
        ]);

        $studentsA = [
            ['name' => 'Dara', 'parent_name' => 'Sok Dara', 'parent_phone' => '012 345 678'],
            ['name' => 'Vanna', 'parent_name' => 'Chan Vanna', 'parent_phone' => '012 111 222'],
            ['name' => 'Sokha', 'parent_name' => 'Sok Dara', 'parent_phone' => '012 345 678'],
            ['name' => 'Ratha', 'parent_name' => 'Kim Ratha', 'parent_phone' => '012 333 444'],
            ['name' => 'Sopheak', 'parent_name' => 'Lim Sopheak', 'parent_phone' => '012 555 666'],
            ['name' => 'Lina', 'parent_name' => 'Duong Lina', 'parent_phone' => '012 777 888'],
            ['name' => 'Pisey', 'parent_name' => 'Meas Pisey', 'parent_phone' => '012 999 000'],
            ['name' => 'Lysa', 'parent_name' => 'Nop Lysa', 'parent_phone' => '016 222 333'],
            ['name' => 'Kimseang', 'parent_name' => 'Sao Kimseang', 'parent_phone' => '017 444 555'],
            ['name' => 'Sreypov', 'parent_name' => 'Chea Sreypov', 'parent_phone' => '018 666 777'],
            ['name' => 'Vireak', 'parent_name' => 'Heng Vireak', 'parent_phone' => '019 888 999'],
            ['name' => 'Malin', 'parent_name' => 'Suon Malin', 'parent_phone' => '015 123 456'],
        ];

        foreach ($studentsA as $data) {
            $student = Student::create([
                'name' => $data['name'],
                'class_id' => $this->classA->id,
                'parent_name' => $data['parent_name'],
                'parent_phone' => $data['parent_phone'],
                'is_active' => true,
            ]);

            // Link the demo parent account to Dara and Sokha.
            if (in_array($data['name'], ['Dara', 'Sokha'], true)) {
                $student->update(['parent_user_id' => $this->parent->id]);
            }
        }

        foreach (['Sona', 'Visal', 'Rina'] as $name) {
            Student::create([
                'name' => $name,
                'class_id' => $classB->id,
                'parent_name' => "Parent of $name",
                'parent_phone' => '012 000 111',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Recreates the frozen end-to-end example (section 21) for YESTERDAY
     * so every final state is already visible in the reports.
     */
    private function seedYesterdayWorkflow(): void
    {
        $yesterday = Carbon::yesterday();
        $dayBefore = Carbon::yesterday()->subDay();
        $students = $this->classA->students()->get()->keyBy('name');

        // ------------------------------------------------- 2 days ago (normal day)
        $normalSession = AttendanceSession::create([
            'class_id' => $this->classA->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => $dayBefore->toDateString(),
            'opened_at' => $dayBefore->copy()->setTime(8, 0),
            'submitted_at' => $dayBefore->copy()->setTime(8, 6),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        foreach ($students as $student) {
            Attendance::create([
                'attendance_session_id' => $normalSession->id,
                'student_id' => $student->id,
                'status' => Attendance::STATUS_PRESENT,
                'marked_by' => $this->teacher->id,
                'marked_at' => $dayBefore->copy()->setTime(8, 5),
                'case_status' => Attendance::CASE_CLOSED,
                'case_closed_at' => $dayBefore->copy()->setTime(8, 6),
                'final_status' => Attendance::FINAL_PRESENT,
                'finalized_by' => $this->teacher->id,
                'finalized_at' => $dayBefore->copy()->setTime(8, 6),
            ]);
        }

        // ---------------------------------------------- yesterday (full workflow)
        // 07:30 — parent of Dara asks permission; Admin approves.
        $daraPermission = Permission::create([
            'student_id' => $students['Dara']->id,
            'class_id' => $this->classA->id,
            'attendance_date' => $yesterday->toDateString(),
            'requested_by' => 'Sok Dara (parent)',
            'requested_by_type' => Permission::REQUESTED_BY_PARENT,
            'reason' => 'Medical appointment',
            'status' => Permission::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
            'approved_at' => $yesterday->copy()->setTime(7, 40),
            'admin_note' => 'Verified medical document.',
        ]);

        // 08:00 — teacher opens and submits attendance at 08:05.
        $yesterdaySession = AttendanceSession::create([
            'class_id' => $this->classA->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => $yesterday->toDateString(),
            'opened_at' => $yesterday->copy()->setTime(8, 0),
            'submitted_at' => $yesterday->copy()->setTime(8, 5),
            'status' => AttendanceSession::STATUS_SUBMITTED,
        ]);

        $teacherMarks = [
            'Dara' => Attendance::STATUS_PERMISSION,   // locked
            'Vanna' => Attendance::STATUS_PRESENT,
            'Sokha' => Attendance::STATUS_PRESENT,
            'Ratha' => Attendance::STATUS_ABSENT,
            'Sopheak' => Attendance::STATUS_ABSENT,
            'Lina' => Attendance::STATUS_ABSENT,
            'Pisey' => Attendance::STATUS_PRESENT,
            'Lysa' => Attendance::STATUS_PRESENT,
            'Kimseang' => Attendance::STATUS_PRESENT,
            'Sreypov' => Attendance::STATUS_ABSENT,
            'Vireak' => Attendance::STATUS_PRESENT,
            'Malin' => Attendance::STATUS_PRESENT,
        ];

        foreach ($teacherMarks as $name => $status) {
            $isLocked = $status === Attendance::STATUS_PERMISSION;

            Attendance::create([
                'attendance_session_id' => $yesterdaySession->id,
                'student_id' => $students[$name]->id,
                'status' => $status,
                'marked_by' => $isLocked ? null : $this->teacher->id,
                'marked_at' => $isLocked ? null : $yesterday->copy()->setTime(8, 4),
                'is_locked' => $isLocked,
                'locked_by' => $isLocked ? $this->admin->id : null,
                'locked_at' => $isLocked ? $yesterday->copy()->setTime(7, 40) : null,
                'lock_reason' => $isLocked ? 'Approved parent permission' : null,
                'permission_id' => $isLocked ? $daraPermission->id : null,
                'case_status' => $isLocked || $status === Attendance::STATUS_PRESENT
                    ? Attendance::CASE_CLOSED : Attendance::CASE_PENDING,
                'final_status' => $isLocked ? Attendance::FINAL_EXCUSED : null,
                'finalized_by' => $isLocked ? $this->admin->id : null,
                'finalized_at' => $isLocked ? $daraPermission->approved_at : null,
            ]);
        }

        // 08:20+ — Student Affairs closes LATE cases for Ratha and Sreypov.
        $ratha = $yesterdaySession->attendances()->where('student_id', $students['Ratha']->id)->first();
        $ratha->update([
            'arrived_at' => $yesterday->copy()->setTime(8, 22),
            'minutes_late' => 17,
            'case_status' => Attendance::CASE_CLOSED,
            'case_closed_by' => $this->affairs->id,
            'case_closed_at' => $yesterday->copy()->setTime(8, 30),
            'final_status' => Attendance::FINAL_LATE,
            'finalized_by' => $this->affairs->id,
            'finalized_at' => $yesterday->copy()->setTime(8, 30),
        ]);

        $sreypov = $yesterdaySession->attendances()->where('student_id', $students['Sreypov']->id)->first();
        $sreypov->update([
            'arrived_at' => $yesterday->copy()->setTime(8, 10),
            'minutes_late' => 5,
            'case_status' => Attendance::CASE_CLOSED,
            'case_closed_by' => $this->affairs->id,
            'case_closed_at' => $yesterday->copy()->setTime(8, 15),
            'final_status' => Attendance::FINAL_LATE,
            'finalized_by' => $this->affairs->id,
            'finalized_at' => $yesterday->copy()->setTime(8, 15),
        ]);

        // Student Affairs escalated the two that never arrived -> Admin.
        $sopheak = $yesterdaySession->attendances()->where('student_id', $students['Sopheak']->id)->first();
        $sopheak->update(['case_status' => Attendance::CASE_ESCALATED]);

        $lina = $yesterdaySession->attendances()->where('student_id', $students['Lina']->id)->first();
        $lina->update(['case_status' => Attendance::CASE_ESCALATED]);

        // Admin — Sopheak's parent says "My child is sick" -> valid -> excused.
        $sopheakPermission = Permission::create([
            'student_id' => $students['Sopheak']->id,
            'class_id' => $this->classA->id,
            'attendance_date' => $yesterday->toDateString(),
            'requested_by' => 'Lim Sopheak (parent)',
            'requested_by_type' => Permission::REQUESTED_BY_ADMIN,
            'reason' => 'My child is sick',
            'status' => Permission::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
            'approved_at' => $yesterday->copy()->setTime(8, 40),
            'admin_note' => 'Called parent — reason verified as valid.',
        ]);
        $sopheak->update([
            'permission_id' => $sopheakPermission->id,
            'final_status' => Attendance::FINAL_EXCUSED,
            'finalized_by' => $this->admin->id,
            'finalized_at' => $yesterday->copy()->setTime(8, 40),
            'admin_note' => 'Called parent — reason verified as valid.',
        ]);

        // Admin — Lina's parent says "She wanted to stay home" -> invalid -> AWP.
        $lina->update([
            'final_status' => Attendance::FINAL_ABSENT_WITHOUT_PERMISSION,
            'finalized_by' => $this->admin->id,
            'finalized_at' => $yesterday->copy()->setTime(8, 50),
            'admin_note' => 'Parent said child did not want to come to school.',
        ]);
    }

    /**
     * TODAY — a pending permission (section 2 example) and an open session,
     * so you can drive the live workflow in the browser.
     */
    private function seedTodayLiveState(): void
    {
        $today = Carbon::today();
        $students = $this->classA->students()->get()->keyBy('name');
        $dara = $students['Dara'];
        $sokha = $students['Sokha'];

        // Section 2: parent requested permission for Sokha at 07:15 -> Pending for Admin.
        Permission::create([
            'student_id' => $sokha->id,
            'class_id' => $this->classA->id,
            'attendance_date' => $today->toDateString(),
            'requested_by' => 'Sok Dara (parent)',
            'requested_by_type' => Permission::REQUESTED_BY_PARENT,
            'reason' => 'Medical appointment',
            'status' => Permission::STATUS_PENDING,
            'created_at' => $today->copy()->setTime(7, 15),
            'updated_at' => $today->copy()->setTime(7, 15),
        ]);

        // Teacher already opened today's session before class (08:00).
        $openSession = AttendanceSession::create([
            'class_id' => $this->classA->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => $today->toDateString(),
            'opened_at' => $today->copy()->setTime(8, 0),
            'status' => AttendanceSession::STATUS_OPEN,
        ]);

        // Dara's permission is approved + locked in today's open session (live Rule 9/10).
        $daraPermission = Permission::create([
            'student_id' => $dara->id,
            'class_id' => $this->classA->id,
            'attendance_date' => $today->toDateString(),
            'requested_by' => 'Sok Dara (parent)',
            'requested_by_type' => Permission::REQUESTED_BY_PARENT,
            'reason' => 'Medical appointment',
            'status' => Permission::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
            'approved_at' => $today->copy()->setTime(7, 50),
            'admin_note' => 'Verified medical document.',
        ]);

        foreach ($students as $student) {
            $isDara = $student->id === $dara->id;

            Attendance::create([
                'attendance_session_id' => $openSession->id,
                'student_id' => $student->id,
                'status' => $isDara ? Attendance::STATUS_PERMISSION : null,
                'is_locked' => $isDara,
                'locked_by' => $isDara ? $this->admin->id : null,
                'locked_at' => $isDara ? $today->copy()->setTime(7, 50) : null,
                'lock_reason' => $isDara ? 'Approved parent permission' : null,
                'permission_id' => $isDara ? $daraPermission->id : null,
                'case_status' => Attendance::CASE_PENDING,
                'final_status' => $isDara ? Attendance::FINAL_EXCUSED : null,
                'finalized_by' => $isDara ? $this->admin->id : null,
                'finalized_at' => $isDara ? $today->copy()->setTime(7, 50) : null,
            ]);
        }
    }
}
