<?php

namespace Basics13\Tests\Browser\Users;

use Basics13\Models\User;
use Laravel\Dusk\Browser;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * Impersonation starts from the Alpine confirm modal of the user list. Leaving it is a button of the
 * host shell, so the host suite covers that half.
 */
class UserImpersonationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_the_super_admin_impersonates_a_user_from_the_list(): void
    {
        $superAdmin = $this->user(['name' => 'Super account']);
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $otherSuperAdmin = User::factory()->superAdmin()->create(['name' => 'Other super account']);
        $target = User::factory()->create(['name' => 'Target account']);

        $this->browse(function (Browser $browser) use ($superAdmin, $otherSuperAdmin, $target): void {
            $browser->loginAs($superAdmin)
                ->visit('/users')
                ->assertMissing("[data-test='user-impersonate-{$superAdmin->getKey()}']")
                ->assertMissing("[data-test='user-impersonate-{$otherSuperAdmin->getKey()}']")
                ->click("[data-test='user-impersonate-{$target->getKey()}']")
                ->waitFor('dialog[open] [data-test="user-confirm-submit"]')
                ->assertSeeIn('dialog[open]', __('basics13::messages.impersonate_confirm_title', ['name' => $target->name]))
                ->click('dialog[open] [data-test="user-confirm-submit"]')
                ->waitForLocation('/dashboard')
                ->assertAuthenticatedAs($target);

            $this->assertSame([], array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                fn (array $entry): bool => $entry['level'] === 'SEVERE',
            )));
        });
    }

    public function test_a_user_who_is_not_a_super_admin_sees_no_impersonate_action(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Target account']);

        $this->browse(function (Browser $browser) use ($admin, $target): void {
            $browser->loginAs($admin)
                ->visit('/users')
                ->assertSee($target->name)
                ->assertMissing("[data-test='user-impersonate-{$target->getKey()}']");
        });
    }
}
