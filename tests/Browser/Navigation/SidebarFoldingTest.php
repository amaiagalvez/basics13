<?php

namespace Basics13\Tests\Browser\Navigation;

use Laravel\Dusk\Browser;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class SidebarFoldingTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const AUXILIARY_TOGGLE = '[data-test="nav-group-basics-auxiliary"] > button';

    public function test_a_folded_group_stays_folded_and_opens_while_it_holds_the_current_page(): void
    {
        $user = $this->admin();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/users')
                ->script('localStorage.clear()');

            $browser->refresh()
                ->waitFor('[data-test="nav-user-types"]')
                ->assertSeeIn('[data-test="nav-group-basics13"]', __('Management'))
                ->assertSeeIn('[data-test="nav-group-basics-auxiliary"]', __('Auxiliary tables'))
                ->click(self::AUXILIARY_TOGGLE)
                ->waitUntilMissing('[data-test="nav-user-types"]')
                ->assertAttribute(self::AUXILIARY_TOGGLE, 'aria-expanded', 'false')

                // Folded after wire:navigate, and the first click opens it again.
                ->click('[data-test="nav-roles"]')
                ->waitForLocation('/roles')
                ->assertMissing('[data-test="nav-user-types"]')
                ->click(self::AUXILIARY_TOGGLE)
                ->waitFor('[data-test="nav-user-types"]')
                ->assertAttribute(self::AUXILIARY_TOGGLE, 'aria-expanded', 'true')
                ->click(self::AUXILIARY_TOGGLE)
                ->waitUntilMissing('[data-test="nav-user-types"]')

                // Folded after a full load as well.
                ->refresh()
                ->assertMissing('[data-test="nav-user-types"]')

                // Open while it holds the current page, folded again once the page is elsewhere.
                ->visit('/user-types')
                ->assertVisible('[data-test="nav-user-types"]')
                ->click('[data-test="nav-users"]')
                ->waitForLocation('/users')
                ->assertMissing('[data-test="nav-user-types"]');

            $this->assertSame([], array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                fn (array $entry): bool => $entry['level'] === 'SEVERE',
            )));
        });
    }
}
