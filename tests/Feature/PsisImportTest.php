<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Services\PsisSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PsisImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tempSqlFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempSqlFile = tempnam(sys_get_temp_dir(), 'psis_test_').'.sql';

        $dummySql = <<<'SQL'
-- Mock Academic Year
INSERT INTO `rms_academicyear` VALUES (5,2026,2027,'3,2,1,4',1,1,1,'2026-06-09','2026-06-13');

-- Mock Group (Class 10A, Grade 10)
INSERT INTO `rms_group` VALUES (101,NULL,1,'10A',NULL,0,NULL,NULL,'5',NULL,NULL,3,10,NULL,NULL,NULL,NULL,1,NULL,'1','2026-06-09',1,1,NULL,NULL,0,0.00,0.00,0.00,0,1,NULL,NULL,NULL,1,0);

-- Mock Enrollment (stu_id 501 into group 101)
INSERT INTO `rms_group_detail_student` VALUES (1,1,1,1,'0000-00-00','0000-00-00',1,0,1,1,'note',1,5,3,10,0,1,'2026-06-09 00:00:00','2026-06-09 00:00:00',1,0,4,1,0.00,0,0,0.00,101,NULL,501,1,0,0,1,0,0,NULL,0,0);

-- Mock Student
INSERT INTO `rms_student` VALUES (501,1,1,NULL,NULL,'សុខ ដារ៉ា','Sok','Dara',NULL,'S-2026-001',NULL,1,NULL,NULL,NULL,'2009-05-15',NULL,'Phnom Penh',NULL,'012345678',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Sok Father','សុក ឪពុក',NULL,NULL,NULL,'012999888',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'hash','photo.png',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-09 00:00:00','2026-06-09 00:00:00',NULL,'2026-06-09',1,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,1,1,NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,0,0,NULL,NULL,0,10,1,2,NULL,0.00,0);
SQL;

        file_put_contents($this->tempSqlFile, $dummySql);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempSqlFile)) {
            unlink($this->tempSqlFile);
        }

        parent::tearDown();
    }

    public function test_dry_run_does_not_modify_database(): void
    {
        $service = app(PsisSyncService::class);
        $stats = $service->syncFromSqlFile($this->tempSqlFile, '2026-2027', dryRun: true);

        $this->assertTrue($stats['dry_run']);
        $this->assertSame(1, $stats['classes_found']);
        $this->assertSame(1, $stats['students_found']);
        $this->assertSame(0, ClassRoom::count());
        $this->assertSame(0, Student::count());
    }

    public function test_sync_imports_classes_and_students(): void
    {
        $service = app(PsisSyncService::class);
        $stats = $service->syncFromSqlFile($this->tempSqlFile, '2026-2027', dryRun: false);

        $this->assertSame(1, $stats['classes_created']);
        $this->assertSame(1, $stats['students_created']);

        $class = ClassRoom::first();
        $this->assertNotNull($class);
        $this->assertSame('10A', $class->name);
        $this->assertSame('Grade 10', $class->grade);
        $this->assertSame(101, $class->psis_group_id);

        $student = Student::first();
        $this->assertNotNull($student);
        $this->assertSame('Sok Dara', $student->name);
        $this->assertSame('សុខ ដារ៉ា', $student->khmer_name);
        $this->assertSame('S-2026-001', $student->student_code);
        $this->assertSame('M', $student->gender);
        $this->assertSame('2009-05-15', $student->dob?->format('Y-m-d'));
        $this->assertSame('Sok Father', $student->parent_name);
        $this->assertSame('012999888', $student->parent_phone);
        $this->assertSame(501, $student->psis_student_id);
        $this->assertSame($class->id, $student->class_id);
    }

    public function test_sync_is_idempotent_and_updates_without_duplicates(): void
    {
        $service = app(PsisSyncService::class);
        $service->syncFromSqlFile($this->tempSqlFile, '2026-2027', dryRun: false);

        $this->assertSame(1, ClassRoom::count());
        $this->assertSame(1, Student::count());

        // Run sync a second time
        $stats = $service->syncFromSqlFile($this->tempSqlFile, '2026-2027', dryRun: false);

        $this->assertSame(0, $stats['classes_created']);
        $this->assertSame(1, $stats['classes_updated']);
        $this->assertSame(0, $stats['students_created']);
        $this->assertSame(1, $stats['students_updated']);

        $this->assertSame(1, ClassRoom::count());
        $this->assertSame(1, Student::count());
    }

    public function test_artisan_command_executes_successfully(): void
    {
        $this->artisan('psis:import', [
            '--file' => $this->tempSqlFile,
            '--year' => '2026-2027',
        ])
            ->assertSuccessful();

        $this->assertSame(1, ClassRoom::count());
        $this->assertSame(1, Student::count());
    }
}
