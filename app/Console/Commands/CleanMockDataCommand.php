<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CleanMockDataCommand extends Command
{
    protected $signature = 'psis:clean-mock {--force : Force deletion without confirmation}';

    protected $description = 'Remove old mock students (e.g. Dara, Kimseang) and their demo attendance/permission records, keeping only real PSIS data.';

    public function handle(): int
    {
        $mockStudents = Student::whereNull('psis_student_id')->get();

        if ($mockStudents->isEmpty()) {
            $this->info('No mock students found. All student records in the database are real PSIS data.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('           PSIS Mock Data Cleanup Utility           ');
        $this->info('====================================================');
        $this->newLine();

        $this->warn("Found {$mockStudents->count()} mock student(s) to remove:");

        $tableData = $mockStudents->map(fn (Student $s) => [
            'ID' => $s->id,
            'Name' => $s->name,
            'Class' => $s->classRoom?->name ?? '—',
            'Parent' => $s->parent_name ?? '—',
            'Campus' => $s->campus?->code ?? 'None (Mock)',
        ])->toArray();

        $this->table(['ID', 'Name', 'Class', 'Parent', 'Campus'], $tableData);

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to permanently delete these mock students and their demo attendance records?')) {
            $this->warn('Cleanup cancelled by user.');

            return self::FAILURE;
        }

        $mockStudentIds = $mockStudents->pluck('id')->toArray();

        $mockAttendanceIds = Attendance::whereIn('student_id', $mockStudentIds)->pluck('id')->toArray();
        $mockLogsCount = AttendanceLog::whereIn('attendance_id', $mockAttendanceIds)->count();
        $mockAttendancesCount = count($mockAttendanceIds);
        $mockPermissionsCount = Permission::whereIn('student_id', $mockStudentIds)->count();

        DB::transaction(function () use ($mockStudentIds, $mockAttendanceIds) {
            // 1. Delete attendance logs for mock attendances
            AttendanceLog::whereIn('attendance_id', $mockAttendanceIds)->delete();

            // 2. Delete mock attendances
            Attendance::whereIn('id', $mockAttendanceIds)->delete();

            // 3. Delete permissions for mock students
            Permission::whereIn('student_id', $mockStudentIds)->delete();

            // 4. Delete demo sessions that now have no attendances
            AttendanceSession::whereDoesntHave('attendances')->delete();

            // 5. Delete mock students
            Student::whereIn('id', $mockStudentIds)->delete();
        });

        // Clear dashboard cache
        Cache::flush();

        $realStudentsCount = Student::whereNotNull('psis_student_id')->count();
        $realClassesCount = ClassRoom::whereNotNull('psis_group_id')->count();
        $remainingSessionsCount = AttendanceSession::count();

        $this->newLine();
        $this->info('Mock data successfully removed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Mock Students Deleted', count($mockStudentIds)],
                ['Demo Attendances Deleted', $mockAttendancesCount],
                ['Demo Attendance Logs Deleted', $mockLogsCount],
                ['Demo Permissions Deleted', $mockPermissionsCount],
                ['Real PSIS Students Retained', $realStudentsCount],
                ['Real Classes Retained', $realClassesCount],
                ['Remaining Attendance Sessions', $remainingSessionsCount],
            ]
        );

        return self::SUCCESS;
    }
}
