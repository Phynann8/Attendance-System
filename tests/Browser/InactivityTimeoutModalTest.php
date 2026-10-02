<?php

namespace Tests\Browser;

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InactivityTimeoutModalTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_inactivity_timeout_modal_renders_and_responds_to_stay_logged_in(): void
    {
        $campus = Campus::create([
            'name_en' => 'Chhouk Va Campus',
            'name_kh' => 'សាខា ឈូកវ៉ា',
            'code' => 'CHV',
            'is_active' => true,
        ]);

        $teacherRole = Role::create([
            'name' => 'Teacher',
            'slug' => User::ROLE_TEACHER,
            'is_system' => true,
        ]);

        $user = User::create([
            'name' => 'Teacher Test',
            'email' => 'teacher.test@school.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_TEACHER,
            'role_id' => $teacherRole->id,
            'campus_id' => $campus->id,
            'is_active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertPresent('#inactivityModal')
                ->assertPresent('#inactivitySeconds')
                ->assertPresent('#inactivityStayBtn')
                // 1. Trigger modal via client JS
                ->script("document.getElementById('inactivityModal').style.display = 'flex';");

            $browser->pause(200)
                ->assertVisible('#inactivityModal')
                // 2. Click "Stay Logged In" button
                ->click('#inactivityStayBtn')
                ->pause(200)
                // 3. Assert modal is closed
                ->assertMissing('#inactivityModal:visible');
        });
    }
}
