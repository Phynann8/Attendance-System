<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Role $adminRole;

    private Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrator',
            'slug' => User::ROLE_ADMIN,
            'is_system' => true,
        ]);

        $this->teacherRole = Role::create([
            'name' => 'Teacher',
            'slug' => User::ROLE_TEACHER,
            'is_system' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@school.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@school.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
            'role_id' => $this->teacherRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_export_attendance_report_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('attendance-report-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_admin_can_filter_reports_by_date_range_and_class(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
            'final_status' => 'present',
        ]));

        $response->assertOk()
            ->assertSee('Attendance Intelligence')
            ->assertSee('Attendance Records');
    }

    public function test_admin_can_export_report_csv_with_date_range_and_class_filter(): void
    {
        $start = now()->subDays(5)->toDateString();
        $end = now()->toDateString();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'start_date' => $start,
            'end_date' => $end,
            'final_status' => 'present',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString("attendance-report-{$start}-to-{$end}.csv", (string) $response->headers->get('content-disposition'));
    }

    public function test_non_admin_cannot_export_report(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('admin.reports.export'));

        $response->assertStatus(403);
    }

    public function test_admin_can_export_attendance_report_xlsx(): void
    {
        $start = now()->subDays(3)->toDateString();
        $end = now()->toDateString();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'format' => 'xlsx',
            'start_date' => $start,
            'end_date' => $end,
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));
    }

    public function test_admin_export_with_pdf_format_redirects_to_printable_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'format' => 'pdf',
            'start_date' => now()->toDateString(),
        ]));

        $response->assertRedirect(route('admin.reports.print', [
            'format' => 'pdf',
            'start_date' => now()->toDateString(),
        ]));
    }

    public function test_admin_can_view_printable_school_board_report(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('Official Attendance Register');
        $response->assertSee('window.print()', false);
        $response->assertSee('Attendance Rate');
        $response->assertSee('Homeroom / Subject Teacher');
        $response->assertSee('Campus Director / Principal');
    }

    public function test_teacher_cannot_access_printable_school_board_report(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.reports.print'));

        $response->assertStatus(403);
    }
}
