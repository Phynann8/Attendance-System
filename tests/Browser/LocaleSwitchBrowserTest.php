<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LocaleSwitchBrowserTest extends DuskTestCase
{
    public function test_user_can_switch_locale_between_khmer_and_english(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/locale/en')
                ->visit('/login')
                ->assertSee('Sign in')
                ->visit('/locale/km')
                ->visit('/login')
                ->assertSee('ចូលប្រព័ន្ធ');
        });
    }
}
