<?php

namespace Tests\Feature;

use App\Jobs\LogAuditEventJob;
use App\Models\AttendanceLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_admin_can_view_audit_logs(): void
    {
        AttendanceLog::create([
            'user_id' => $this->admin->id,
            'action' => 'session_submitted',
            'details' => 'Morning session submitted for Grade 10-A',
        ]);

        AttendanceLog::create([
            'user_id' => $this->admin->id,
            'action' => 'permission_approved',
            'details' => 'Sick leave approved for student Jane Doe',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('System Audit Log');
        $response->assertSee('Morning session submitted for Grade 10-A');
        $response->assertSee('Sick leave approved for student Jane Doe');
    }

    public function test_admin_can_filter_audit_logs(): void
    {
        AttendanceLog::create([
            'user_id' => $this->admin->id,
            'action' => 'session_submitted',
            'details' => 'Morning session submitted for Grade 10-A',
        ]);

        AttendanceLog::create([
            'user_id' => $this->admin->id,
            'action' => 'user_soft_deleted',
            'details' => 'Deactivated user account',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', ['action' => 'session_submitted']));

        $response->assertStatus(200);
        $response->assertSee('Morning session submitted for Grade 10-A');
        $response->assertDontSee('Deactivated user account');
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('admin.audit-logs.index'));

        $response->assertStatus(403);
    }

    public function test_audit_service_can_dispatch_job_asynchronously(): void
    {
        Queue::fake();

        AuditService::dispatch('test.async_action', details: ['key' => 'value']);

        Queue::assertPushed(LogAuditEventJob::class, function ($job) {
            return $job->action === 'test.async_action';
        });

        // Test running the job directly creates log
        $job = new LogAuditEventJob('test.async_action', userId: $this->admin->id, details: '{"key":"value"}');
        $log = $job->handle();

        $this->assertInstanceOf(AttendanceLog::class, $log);
        $this->assertDatabaseHas('attendance_logs', [
            'action' => 'test.async_action',
            'user_id' => $this->admin->id,
        ]);
    }
}
