<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@school.test',
            'password' => 'password',
            'role' => 'teacher',
        ]);
    }

    public function test_user_can_switch_locale_to_khmer(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('locale.switch', 'km'));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'km');

        // Fetch dashboard with the km session
        $pageResponse = $this->actingAs($this->teacher)
            ->withSession(['locale' => 'km'])
            ->get(route('dashboard'));

        $pageResponse->assertOk();
        $this->assertSame('km', app()->getLocale());
        $pageResponse->assertSee('ផ្ទាំងគ្រប់គ្រង');
    }

    public function test_user_can_switch_locale_to_english(): void
    {
        $response = $this->actingAs($this->teacher)
            ->withSession(['locale' => 'km'])
            ->get(route('locale.switch', 'en'));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'en');

        $pageResponse = $this->actingAs($this->teacher)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'));

        $pageResponse->assertOk();
        $this->assertSame('en', app()->getLocale());
        $pageResponse->assertSee('Dashboard');
    }

    public function test_invalid_locale_is_ignored(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('locale.switch', 'xyz'));

        $response->assertRedirect();
        $this->assertNull(session('locale'));
    }
}
