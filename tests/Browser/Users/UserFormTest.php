<?php

namespace Basics13\Tests\Browser\Users;

use Basics13\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Support\Facades\Hash;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * The drawer decides in Alpine which credentials it offers: only the account holder sees an editable
 * email and the password fields, so a regression there would expose another account's credentials.
 */
class UserFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const NAME_SELECTOR = 'dialog[open] [data-test="user-name"]';

    private const EMAIL_SELECTOR = 'dialog[open] [data-test="user-email"]';

    private const PASSWORD_SELECTOR = 'dialog[open] [data-test="user-password"]';

    private const PASSWORD_CONFIRMATION_SELECTOR = 'dialog[open] [data-test="user-password-confirmation"]';

    private const OWN_ACCOUNT_NOTICE_SELECTOR = 'dialog[open] [data-test="user-own-account-only"]';

    private const SUBMIT_SELECTOR = 'dialog[open] [data-test="user-submit"]';

    private const STATUS_SELECTOR = '[data-test="user-status"]';

    public function test_another_account_is_edited_without_its_email_or_password(): void
    {
        $user = $this->user();
        $other = User::factory()->create(['name' => 'Other account', 'email' => 'other@example.test']);
        $passwordHash = $other->password;

        $this->browse(function (Browser $browser) use ($user, $other): void {
            $browser->loginAs($user)
                ->visit('/users')
                ->click("[data-test='user-edit-{$other->getKey()}']")
                ->waitFor(self::NAME_SELECTOR)
                ->assertInputValue(self::EMAIL_SELECTOR, 'other@example.test')
                ->assertScript($this->readOnly(self::EMAIL_SELECTOR), true)
                ->assertVisible(self::OWN_ACCOUNT_NOTICE_SELECTOR)
                ->assertMissing(self::PASSWORD_SELECTOR)
                ->assertMissing(self::PASSWORD_CONFIRMATION_SELECTOR)
                ->clear(self::NAME_SELECTOR)
                ->type(self::NAME_SELECTOR, 'Renamed account')
                ->click(self::SUBMIT_SELECTOR)
                ->waitFor(self::STATUS_SELECTOR)
                ->assertSee('Renamed account');
        });

        $other->refresh();
        $this->assertSame('Renamed account', $other->name);
        $this->assertSame('other@example.test', $other->email);
        $this->assertSame($passwordHash, $other->password);
    }

    public function test_the_account_holder_changes_its_own_password_from_the_drawer(): void
    {
        $user = $this->user(['email' => 'holder@example.test']);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/users')
                ->click("[data-test='user-edit-{$user->getKey()}']")
                ->waitFor(self::NAME_SELECTOR)
                ->assertScript($this->readOnly(self::EMAIL_SELECTOR), false)
                ->assertMissing(self::OWN_ACCOUNT_NOTICE_SELECTOR)
                ->assertVisible(self::PASSWORD_SELECTOR)
                ->assertSeeIn('dialog[open]', __('New password'))
                ->assertSeeIn('dialog[open]', __('Leave empty to keep the current password.'))
                ->assertInputValue(self::PASSWORD_SELECTOR, '')
                ->type(self::PASSWORD_SELECTOR, 'brand-new-password')
                ->type(self::PASSWORD_CONFIRMATION_SELECTOR, 'brand-new-password')
                ->click(self::SUBMIT_SELECTOR)
                ->waitFor(self::STATUS_SELECTOR);
        });

        $this->assertTrue(Hash::check('brand-new-password', $user->refresh()->getAuthPassword()));
    }

    public function test_an_account_is_created_with_its_password_from_the_drawer(): void
    {
        $user = $this->user();
        $other = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $other): void {
            $browser->loginAs($user)
                // Opening another account first proves the create action resets the credentials.
                ->visit('/users')
                ->click("[data-test='user-edit-{$other->getKey()}']")
                ->waitFor(self::NAME_SELECTOR)
                ->assertMissing(self::PASSWORD_SELECTOR)
                ->visit('/users')
                ->click('[data-test="user-create-button"]')
                ->waitFor(self::NAME_SELECTOR)
                ->assertScript($this->readOnly(self::EMAIL_SELECTOR), false)
                ->assertVisible(self::PASSWORD_SELECTOR)
                ->assertScript('document.querySelector('.json_encode(self::PASSWORD_SELECTOR).').required', true)
                ->type(self::NAME_SELECTOR, 'Created account')
                ->type(self::EMAIL_SELECTOR, 'created@example.test')
                ->type(self::PASSWORD_SELECTOR, 'created-password')
                ->type(self::PASSWORD_CONFIRMATION_SELECTOR, 'created-password')
                ->click(self::SUBMIT_SELECTOR)
                ->waitFor(self::STATUS_SELECTOR)
                ->assertSee('Created account');
        });

        $created = User::query()->where('email', 'created@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('created-password', $created->password));
    }

    private function readOnly(string $selector): string
    {
        return 'document.querySelector('.json_encode($selector).').readOnly';
    }
}
