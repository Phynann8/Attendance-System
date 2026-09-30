<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Security',
            'email' => 'admin.2fa@test.test',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher Standard',
            'email' => 'teacher.std@test.test',
            'password' => 'password123',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);
    }

    public function test_admin_login_redirects_to_2fa_challenge_prompt(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => 'admin.2fa@test.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('2fa.challenge'));
        $this->assertGuest();
        $this->assertSame($this->admin->id, session('2fa:user_id'));
    }

    public function test_admin_can_verify_valid_6_digit_totp_code_and_access_dashboard(): void
    {
        // 1. Initial login step
        $this->post(route('login.attempt'), [
            'email' => 'admin.2fa@test.test',
            'password' => 'password123',
        ]);

        // Load challenge page (generates secret on first time)
        $challengePage = $this->get(route('2fa.challenge'));
        $challengePage->assertOk()
            ->assertSee('Two-Factor Authentication')
            ->assertSee('Enter 6-Digit Authenticator Code');

        $this->admin->refresh();
        $this->assertNotNull($this->admin->two_factor_secret);

        // Generate the valid TOTP code
        $validCode = TwoFactorAuthService::getCode($this->admin->two_factor_secret);

        // 2. Submit 2FA verification
        $verifyResponse = $this->post(route('2fa.verify'), [
            'code' => $validCode,
        ]);

        $verifyResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);

        $this->admin->refresh();
        $this->assertNotNull($this->admin->two_factor_confirmed_at);
        $this->assertTrue($this->admin->hasTwoFactorEnabled());
    }

    public function test_invalid_totp_code_is_rejected(): void
    {
        $this->post(route('login.attempt'), [
            'email' => 'admin.2fa@test.test',
            'password' => 'password123',
        ]);

        $this->get(route('2fa.challenge'));
        $this->admin->refresh();

        $response = $this->post(route('2fa.verify'), [
            'code' => '999999', // invalid code
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_admin_can_authenticate_with_emergency_recovery_code(): void
    {
        $this->post(route('login.attempt'), [
            'email' => 'admin.2fa@test.test',
            'password' => 'password123',
        ]);

        $this->get(route('2fa.challenge'));
        $this->admin->refresh();

        $recoveryCodes = $this->admin->two_factor_recovery_codes;
        $this->assertNotEmpty($recoveryCodes);
        $chosenCode = $recoveryCodes[0];

        $response = $this->post(route('2fa.verify'), [
            'recovery_code' => $chosenCode,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);

        // Assert the used recovery code was removed
        $this->admin->refresh();
        $this->assertNotContains($chosenCode, $this->admin->two_factor_recovery_codes);
        $this->assertCount(count($recoveryCodes) - 1, $this->admin->two_factor_recovery_codes);
    }

    public function test_non_admin_user_can_login_directly_without_2fa(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => 'teacher.std@test.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->teacher);
    }

    public function test_2fa_challenge_cannot_be_accessed_without_initial_password_verification(): void
    {
        $response = $this->get(route('2fa.challenge'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_cancelling_2fa_challenge_clears_session_and_returns_to_login(): void
    {
        $this->post(route('login.attempt'), [
            'email' => 'admin.2fa@test.test',
            'password' => 'password123',
        ]);

        $this->assertTrue(session()->has('2fa:user_id'));

        $cancelResponse = $this->get(route('2fa.cancel'));
        $cancelResponse->assertRedirect(route('login'));

        $this->assertFalse(session()->has('2fa:user_id'));
    }
}
