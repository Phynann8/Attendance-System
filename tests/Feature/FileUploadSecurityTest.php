<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private Student $child;

    private ClassRoom $class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->parent = User::create([
            'name' => 'Parent User',
            'email' => 'parent.secure@test.test',
            'password' => 'password',
            'role' => 'parent',
        ]);

        $this->class = ClassRoom::create(['name' => '10-A']);

        $this->child = Student::create([
            'name' => 'Student Secure',
            'class_id' => $this->class->id,
            'parent_user_id' => $this->parent->id,
            'is_active' => true,
        ]);
    }

    public function test_valid_png_evidence_upload_is_stored_with_uuid_path(): void
    {
        $file = UploadedFile::fake()->image('doctor_slip.png', 400, 400);

        $response = $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->child->id,
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'category' => Permission::CATEGORY_MEDICAL,
            'reason' => 'Doctor appointment',
            'evidence' => $file,
        ]);

        $response->assertRedirect(route('parent.permissions.index'));
        $permission = Permission::where('student_id', $this->child->id)->first();
        $this->assertNotNull($permission);
        $this->assertNotNull($permission->evidence_path);

        // Verify UUID filename structure: permissions/evidence/{uuid}.png
        $this->assertMatchesRegularExpression('#^permissions/evidence/[a-f0-9\-]+\.png$#i', $permission->evidence_path);
        Storage::disk('public')->assertExists($permission->evidence_path);
    }

    public function test_valid_pdf_evidence_upload_is_stored_successfully(): void
    {
        $file = UploadedFile::fake()->create('medical_cert.pdf', 150, 'application/pdf');

        $response = $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->child->id,
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'category' => Permission::CATEGORY_MEDICAL,
            'reason' => 'Doctor clinic visit',
            'evidence' => $file,
        ]);

        $response->assertRedirect(route('parent.permissions.index'));
        $permission = Permission::where('student_id', $this->child->id)->first();
        $this->assertNotNull($permission->evidence_path);
        $this->assertMatchesRegularExpression('#^permissions/evidence/[a-f0-9\-]+\.pdf$#i', $permission->evidence_path);
        Storage::disk('public')->assertExists($permission->evidence_path);
    }

    public function test_executable_files_are_rejected(): void
    {
        $maliciousFile = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->child->id,
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Test virus upload',
            'evidence' => $maliciousFile,
        ]);

        $response->assertSessionHasErrors(['evidence']);
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_files_with_php_code_signature_are_rejected(): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test_php');
        file_put_contents($tempFilePath, '<?php phpinfo(); ?>');

        $fakePhpAsPng = new UploadedFile(
            $tempFilePath,
            'exploit.png',
            'image/png',
            null,
            true
        );

        $response = $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->child->id,
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Exploit attempt',
            'evidence' => $fakePhpAsPng,
        ]);

        $response->assertSessionHasErrors(['evidence']);
        $this->assertDatabaseCount('permissions', 0);

        if (file_exists($tempFilePath)) {
            @unlink($tempFilePath);
        }
    }

    public function test_files_exceeding_5mb_are_rejected(): void
    {
        // 6000 KB > 5120 KB
        $oversizedFile = UploadedFile::fake()->create('huge_file.pdf', 6000, 'application/pdf');

        $response = $this->actingAs($this->parent)->post(route('parent.permissions.store'), [
            'student_id' => $this->child->id,
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Oversized file test',
            'evidence' => $oversizedFile,
        ]);

        $response->assertSessionHasErrors(['evidence']);
        $this->assertDatabaseCount('permissions', 0);
    }
}
