<?php

namespace Basics13\Tests\Browser\Settings;

use Laravel\Dusk\Browser;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * Deleting the own account goes through a Flux modal that only submits with the current password.
 */
class DeleteAccountTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const PASSWORD_SELECTOR = 'dialog[open] input[wire\\:model="password"]';

    private const CONFIRM_SELECTOR = 'dialog[open] [data-test="confirm-delete-user-button"]';

    public function test_the_account_is_deleted_only_after_confirming_the_current_password(): void
    {
        $user = $this->user();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/settings/profile')
                ->click('[data-test="delete-user-button"]')
                ->waitFor(self::PASSWORD_SELECTOR)
                ->assertSeeIn('dialog[open]', __('Are you sure you want to delete your account?'))
                ->type(self::PASSWORD_SELECTOR, 'wrong-password')
                ->click(self::CONFIRM_SELECTOR)
                ->waitForTextIn('dialog[open]', __('validation.current_password'))
                ->assertPathIs('/settings/profile');

            $this->assertNotSoftDeleted($user);

            $browser->clear(self::PASSWORD_SELECTOR)
                ->type(self::PASSWORD_SELECTOR, 'password')
                ->click(self::CONFIRM_SELECTOR)
                ->waitForLocation('/')
                ->visit('/settings/profile')
                ->assertPathIs('/login');
        });

        $this->assertSoftDeleted($user);
    }

    public function test_cancelling_the_modal_keeps_the_account(): void
    {
        $user = $this->user();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/settings/profile')
                ->click('[data-test="delete-user-button"]')
                ->waitFor(self::PASSWORD_SELECTOR)
                ->type(self::PASSWORD_SELECTOR, 'password')
                ->press(__('Cancel'))
                ->waitUntilMissing('dialog[open]')
                ->assertPathIs('/settings/profile');
        });

        $this->assertNotSoftDeleted($user);
    }
}
