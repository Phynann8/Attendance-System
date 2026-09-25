<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\SystemPermission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private User $superAdmin;

    private User $admin;

    private User $teacher;

    private User $affairs;

    private User $parent;

    private ClassRoom $classA;

    public function run(): void
    {
        $this->seedSchoolSettings();

        if (User::where('email', 'admin@school.test')->exists() || Student::whereNotNull('psis_student_id')->exists()) {
            $this->command?->info('System or PSIS data already present — skipping mock demo data seeding.');

            return;
        }

        $this->seedRolesAndPermissions();
        $this->seedUsers();
        $this->seedClassesAndStudents();
        $this->seedYesterdayWorkflow();
        $this->seedTodayLiveState();

        $this->command?->info('Demo data seeded.');
    }

    private function seedSchoolSettings(): void
    {
        $defaults = [
            'school_name' => ['value' => 'Attendance System Academy', 'group' => 'general', 'description' => 'Official school name displayed on headers and reports.'],
            'school_phone' => ['value' => '+855 12 345 678', 'group' => 'general', 'description' => 'School office contact phone number.'],
            'school_email' => ['value' => 'admin@school.test', 'group' => 'general', 'description' => 'Official administrative email address.'],
            'academic_year' => ['value' => '2026-2027', 'group' => 'academic', 'description' => 'Current active academic year.'],
            'academic_term' => ['value' => 'Semester 1', 'group' => 'academic', 'description' => 'Current academic semester or term.'],
            'class_start_time' => ['value' => '08:00', 'group' => 'attendance', 'description' => 'Standard morning class commencement time (HH:MM).'],
            'late_grace_minutes' => ['value' => '15', 'group' => 'attendance', 'description' => 'Grace period in minutes before counting arrival as late.'],
        ];

        foreach ($defaults as $key => $data) {
            SchoolSetting::firstOrCreate(['key' => $key], $data);
        }
    }

    private function seedRolesAndPermissions(): void
    {
        // 1. Create System Roles
        $superAdminRole = Role::firstOrCreate(
            ['slug' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'description' => 'Full unrestricted platform access and governance.', 'is_system' => true]
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrator', 'description' => 'School administration, absence decisions, and approvals.', 'is_system' => true]
        );

        $teacherRole = Role::firstOrCreate(
            ['slug' => User::ROLE_TEACHER],
            ['name' => 'Teacher', 'description' => 'Homeroom class attendance marking and session submission.', 'is_system' => true]
        );

        $affairsRole = Role::firstOrCreate(
            ['slug' => User::ROLE_STUDENT_AFFAIRS],
            ['name' => 'Student Affairs', 'description' => 'Verification of absent students and late arrival logging.', 'is_system' => true]
        );

        $parentRole = Role::firstOrCreate(
            ['slug' => User::ROLE_PARENT],
            ['name' => 'Parent', 'description' => 'Parent portal for absence requests and history.', 'is_system' => true]
        );

        // 2. Create System Module Permissions
        $permissions = [
            // Super Admin
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'Super Admin', 'description' => 'Create, edit, inactivate, soft-delete users, and assign roles.'],
            ['name' => 'Manage Roles & Permissions', 'slug' => 'roles.manage', 'module' => 'Super Admin', 'description' => 'Create custom roles and configure permitted modules.'],
            // Administration
            ['name' => 'Review & Assign Permissions', 'slug' => 'admin.permissions', 'module' => 'Administration', 'description' => 'Review parent permission requests and assign leaves.'],
            ['name' => 'Review Absence Cases', 'slug' => 'admin.absence', 'module' => 'Administration', 'description' => 'Review escalated absences and record final decisions.'],
            ['name' => 'Manage Students', 'slug' => 'admin.students', 'module' => 'Administration', 'description' => 'Manage student rosters and profiles.'],
            ['name' => 'Manage Classes', 'slug' => 'admin.classes', 'module' => 'Administration', 'description' => 'Manage classroom configurations.'],
            ['name' => 'View Reports', 'slug' => 'admin.reports', 'module' => 'Administration', 'description' => 'View school attendance metrics and historical summaries.'],
            // Teacher
            ['name' => 'Take Attendance', 'slug' => 'teacher.attendance', 'module' => 'Teacher', 'description' => 'Open sessions and record student attendance.'],
            // Student Affairs
            ['name' => 'Verify Late / Absences', 'slug' => 'student_affairs.review', 'module' => 'Student Affairs', 'description' => 'Log late arrivals and escalate unknown absences.'],
            // Parent
            ['name' => 'Submit Permission Requests', 'slug' => 'parent.permissions', 'module' => 'Parent', 'description' => 'Submit student absence requests and check statuses.'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['slug']] = SystemPermission::firstOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // 3. Assign Permissions to Roles
        $superAdminRole->systemPermissions()->sync(collect($permissionModels)->pluck('id'));

        $adminRole->systemPermissions()->sync([
            $permissionModels['admin.permissions']->id,
            $permissionModels['admin.absence']->id,
            $permissionModels['admin.students']->id,
            $permissionModels['admin.classes']->id,
            $permissionModels['admin.reports']->id,
        ]);

        $teacherRole->systemPermissions()->sync([
            $permissionModels['teacher.attendance']->id,
        ]);

        $affairsRole->systemPermissions()->sync([
            $permissionModels['student_affairs.review']->id,
        ]);

        $parentRole->systemPermissions()->sync([
            $permissionModels['parent.permissions']->id,
        ]);
    }

    private function seedUsers(): void
    {
        $superAdminRole = Role::where('slug', User::ROLE_SUPER_ADMIN)->first();
        $adminRole = Role::where('slug', User::ROLE_ADMIN)->first();
        $teacherRole = Role::where('slug', User::ROLE_TEACHER)->first();
        $affairsRole = Role::where('slug', User::ROLE_STUDENT_AFFAIRS)->first();
        $parentRole = Role::where('slug', User::ROLE_PARENT)->first();

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@school.test',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'role_id' => $superAdminRole?->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Office',
            'email' => 'admin@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $adminRole?->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher Dara',
            'email' => 'teacher@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $teacherRole?->id,
            'is_active' => true,
        ]);

        $this->affairs = User::create([
            'name' => 'Student Affairs',
            'email' => 'affairs@school.test',
            'password' => 'password',
            'role' => User::ROLE_STUDENT_AFFAIRS,
            'role_id' => $affairsRole?->id,
            'is_active' => true,
        ]);

        $this->parent = User::create([
            'name' => 'Sok Dara',
            'email' => 'parent@school.test',
            'password' => 'password',
            'role' => User::ROLE_PARENT,
            'role_id' => $parentRole?->id,
            'is_active' => true,
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
