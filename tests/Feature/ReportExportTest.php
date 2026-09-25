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
}
