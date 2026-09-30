<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactivityTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'Teacher Kiosk',
            'email' => 'teacher.kiosk@test.test',
            'password' => 'password',
            'role' => 'teacher',
        ]);
    }

    public function test_authenticated_user_with_active_session_browses_normally(): void
    {
        $response = $this->actingAs($this->teacher)
            ->withSession(['last_activity_time' => time() - 300]) // 5 minutes ago (< 15 mins)
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertAuthenticatedAs($this->teacher);
        $this->assertGreaterThanOrEqual(time() - 2, session('last_activity_time'));
    }

    public function test_authenticated_user_is_logged_out_after_15_minutes_of_inactivity(): void
    {
        $response = $this->actingAs($this->teacher)
            ->withSession(['last_activity_time' => time() - 950]) // 950 seconds ago (> 900s)
            ->get(route('dashboard'));

        $response->assertRedirect(route('login', ['reason' => 'inactivity']));
        $this->assertGuest();

        // Following redirect shows session expiration warning
        $loginResponse = $this->get(route('login', ['reason' => 'inactivity']));
        $loginResponse->assertOk()
            ->assertSee('Your session has expired due to 15 minutes of inactivity');
    }

    public function test_explicit_inactivity_logout_redirects_with_warning(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('logout'), ['reason' => 'inactivity']);

        $response->assertRedirect(route('login', ['reason' => 'inactivity']));
        $this->assertGuest();
        $this->assertTrue(session()->has('warning'));
    }

    public function test_session_ping_endpoint_refreshes_activity_timestamp(): void
    {
        $oldTime = time() - 600;

        $response = $this->actingAs($this->teacher)
            ->withSession(['last_activity_time' => $oldTime])
            ->postJson(route('session.ping'));

        $response->assertOk()
            ->assertJson(['status' => 'active']);

        $this->assertGreaterThan($oldTime, session('last_activity_time'));
    }

    public function test_inactivity_modal_is_rendered_for_authenticated_users(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('inactivityModal', false)
            ->assertSee('Session Inactivity Warning')
            ->assertSee('inactivityStayBtn', false);
    }
}
