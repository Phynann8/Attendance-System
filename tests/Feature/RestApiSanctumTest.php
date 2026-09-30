<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestApiSanctumTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $parent;

    private ClassRoom $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'Teacher Tablet',
            'email' => 'teacher.tablet@test.test',
            'password' => 'password123',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $this->parent = User::create([
            'name' => 'Parent Mobile',
            'email' => 'parent.mobile@test.test',
            'password' => 'password123',
            'role' => User::ROLE_PARENT,
            'is_active' => true,
        ]);

        $this->class = ClassRoom::create([
            'name' => 'Class 7A',
            'teacher_id' => $this->teacher->id,
        ]);

        $this->student = Student::create([
            'name' => 'Student Alex',
            'class_id' => $this->class->id,
            'parent_user_id' => $this->parent->id,
            'is_active' => true,
        ]);
    }

    public function test_login_with_valid_credentials_returns_sanctum_bearer_token(): void
    {
        $response = $this->postJson(route('api.v1.login'), [
            'email' => 'teacher.tablet@test.test',
            'password' => 'password123',
            'device_name' => 'iPad-Classroom-1',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('teacher.tablet@test.test', $response->json('user.email'));
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson(route('api.v1.login'), [
            'email' => 'teacher.tablet@test.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'The provided credentials do not match our records.']);
    }

    public function test_authenticated_user_can_access_profile_and_logout_revokes_token(): void
    {
        $token = $this->teacher->createToken('test-token')->plainTextToken;

        $profileResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route('api.v1.user'));

        $profileResponse->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $this->teacher->id,
                    'email' => 'teacher.tablet@test.test',
                ],
            ]);

        // Revoke token via logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer $token")
            ->postJson(route('api.v1.logout'));

        $logoutResponse->assertOk()
            ->assertJson(['message' => 'Token revoked successfully. Logged out.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        // Subsequent call with revoked token is rejected
        $rejectedResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route('api.v1.user'));

        $rejectedResponse->assertStatus(401);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson(route('api.v1.user'));
        $response->assertStatus(401);
    }

    public function test_teacher_can_fetch_classes_and_students(): void
    {
        $token = $this->teacher->createToken('tablet')->plainTextToken;

        $classesResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route('api.v1.teacher.classes'));

        $classesResponse->assertOk()
            ->assertJsonFragment(['name' => 'Class 7A', 'students_count' => 1]);

        $studentsResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route('api.v1.teacher.students', ['classId' => $this->class->id]));

        $studentsResponse->assertOk()
            ->assertJsonFragment(['name' => 'Student Alex']);
    }

    public function test_teacher_can_mark_attendance_session_via_api(): void
    {
        $token = $this->teacher->createToken('tablet')->plainTextToken;

        $session = AttendanceSession::create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => AttendanceSession::STATUS_OPEN,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson(route('api.v1.teacher.mark', ['sessionId' => $session->id]), [
                'records' => [
                    [
                        'student_id' => $this->student->id,
                        'status' => 'present',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Attendance session submitted successfully.',
                'status' => AttendanceSession::STATUS_SUBMITTED,
                'records_count' => 1,
            ]);

        $attendance = Attendance::where('attendance_session_id', $session->id)->first();
        $this->assertNotNull($attendance);
        $this->assertSame('present', $attendance->status);
        $this->assertSame('present', $attendance->final_status);

        $session->refresh();
        $this->assertSame(AttendanceSession::STATUS_SUBMITTED, $session->status);
    }

    public function test_parent_can_fetch_children_and_attendance_via_api(): void
    {
        $token = $this->parent->createToken('parent-phone')->plainTextToken;

        $childrenResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route('api.v1.parent.children'));

        $childrenResponse->assertOk()
            ->assertJsonFragment(['name' => 'Student Alex']);

        $attendanceResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route('api.v1.parent.child-attendance', ['studentId' => $this->student->id]));

        $attendanceResponse->assertOk()
            ->assertJsonStructure(['student', 'records']);
    }
}
