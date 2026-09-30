<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\User;
use App\Services\ScheduleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TimetableCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private User $teacher;

    private ClassRoom $classRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::create([
            'name_en' => 'Chhouk Va Campus',
            'name_kh' => 'ឈូកវ៉ា',
            'code' => 'CHV',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Campus Admin',
            'email' => 'admin@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_ADMIN,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);
        $this->admin->campuses()->sync([$this->campus->id]);

        $this->teacher = User::create([
            'name' => 'Teacher Dara',
            'email' => 'teacher.dara@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_TEACHER,
            'campus_id' => $this->campus->id,
            'is_active' => true,
        ]);
        $this->teacher->campuses()->sync([$this->campus->id]);

        $this->classRoom = ClassRoom::create([
            'name' => 'Grade 10A',
            'campus_id' => $this->campus->id,
            'grade' => 'Grade 10',
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_schedule_import_service_parses_csv_and_creates_records(): void
    {
        $csvContent = "class_name,day_of_week,period_number,subject,start_time,end_time,teacher_email,is_primary\n".
            "Grade 10A,Monday,1,Mathematics,07:30,08:15,teacher.dara@school.test,1\n".
            "Grade 10A,Monday,2,Physics,08:20,09:05,teacher.dara@school.test,0\n".
            "Grade 10A,Tuesday,1,Khmer Literature,07:30,08:15,,1\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'sched_');
        file_put_contents($tempFile, $csvContent);

        $service = new ScheduleImportService;
        $result = $service->importFromCsv($tempFile, $this->campus->id);

        unlink($tempFile);

        $this->assertEquals(3, $result['total_rows']);
        $this->assertEquals(3, $result['imported']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['skipped']);

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->classRoom->id,
            'day_of_week' => 1,
            'period_number' => 1,
            'subject' => 'Mathematics',
            'start_time' => '07:30',
            'end_time' => '08:15',
            'teacher_id' => $this->teacher->id,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->classRoom->id,
            'day_of_week' => 1,
            'period_number' => 2,
            'subject' => 'Physics',
            'is_primary' => false,
        ]);

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->classRoom->id,
            'day_of_week' => 2,
            'period_number' => 1,
            'subject' => 'Khmer Literature',
            'teacher_id' => $this->classRoom->teacher_id, // fallback to class homeroom teacher
        ]);
    }

    public function test_schedule_import_is_idempotent_and_updates_existing_slots(): void
    {
        // First import
        ClassSchedule::create([
            'class_id' => $this->classRoom->id,
            'day_of_week' => 1,
            'period_number' => 1,
            'subject' => 'Old Subject',
            'teacher_id' => $this->teacher->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $csvContent = "class_name,day_of_week,period_number,subject,start_time,end_time,teacher_email,is_primary\n".
            "Grade 10A,1,1,Updated Advanced Mathematics,08:00,08:50,teacher.dara@school.test,1\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'sched_');
        file_put_contents($tempFile, $csvContent);

        $service = new ScheduleImportService;
        $result = $service->importFromCsv($tempFile, $this->campus->id);

        unlink($tempFile);

        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['updated']);

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->classRoom->id,
            'day_of_week' => 1,
            'period_number' => 1,
            'subject' => 'Updated Advanced Mathematics',
            'is_primary' => true,
        ]);
        $this->assertDatabaseMissing('class_schedules', [
            'subject' => 'Old Subject',
        ]);
    }

    public function test_artisan_command_schedule_import(): void
    {
        $csvContent = "class_name,day_of_week,period_number,subject,start_time,end_time,teacher_email,is_primary\n".
            "Grade 10A,Wednesday,3,Chemistry,09:15,10:00,teacher.dara@school.test,0\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'sched_artisan_');
        file_put_contents($tempFile, $csvContent);

        $this->artisan('schedule:import', [
            'file' => $tempFile,
            '--campus' => 'CHV',
        ])
            ->expectsOutputToContain('Timetable import completed successfully')
            ->assertExitCode(0);

        unlink($tempFile);

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->classRoom->id,
            'day_of_week' => 3,
            'period_number' => 3,
            'subject' => 'Chemistry',
        ]);
    }

    public function test_web_admin_can_download_csv_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.schedules.template'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('class_name,day_of_week,period_number,subject', $response->getContent());
    }

    public function test_web_admin_can_upload_and_import_schedule_csv(): void
    {
        $csvData = "class_name,day_of_week,period_number,subject,start_time,end_time,teacher_email,is_primary\n".
            "Grade 10A,Friday,4,Biology,10:15,11:00,teacher.dara@school.test,0\n";

        $file = UploadedFile::fake()->createWithContent('timetable.csv', $csvData);

        $response = $this->actingAs($this->admin)->post(route('admin.schedules.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $this->classRoom->id,
            'day_of_week' => 5,
            'period_number' => 4,
            'subject' => 'Biology',
        ]);
    }
}
