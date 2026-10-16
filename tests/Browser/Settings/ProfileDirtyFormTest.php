<?php

namespace Basics13\Tests\Browser\Settings;

use Laravel\Dusk\Browser;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProfileDirtyFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        self::assertSame('mysql', config('database.default'));
        self::assertSame('laravel_test', config('database.connections.mysql.database'));
        self::assertNotSame('en', app()->getLocale());
    }

    public function test_settings_save_buttons_only_enable_after_changes_and_navigation_warns(): void
    {
        $user = $this->user();
        $savedName = 'Updated profile name';

        $this->browse(function (Browser $browser) use ($user, $savedName): void {
            $nameInput = 'input[wire\\:model="name"]';
            $saveButtonDisabled = 'document.querySelector(\'[data-test="update-profile-button"]\').disabled';

            $browser->loginAs($user)
                ->visit('/settings/profile')
                ->waitFor('[data-test="update-profile-button"]')
                ->assertScript(
                    $saveButtonDisabled,
                    true,
                )
                ->clear($nameInput)
                ->type($nameInput, $savedName)
                ->assertScript(
                    $saveButtonDisabled,
                    false,
                )
                ->click('a[href$="/settings/appearance"]')
                ->assertDialogOpened(__('You have unsaved changes. Leave without saving?'))
                ->dismissDialog()
                ->assertPathIs('/settings/profile')
                ->click('[data-test="update-profile-button"]')
                ->waitUntil(
                    'document.body.innerText.includes('.json_encode(__('Profile updated.')).')',
                    10,
                )
                ->waitUntil(
                    $saveButtonDisabled,
                    10,
                )
                ->assertScript(
                    $saveButtonDisabled,
                    true,
                )
                ->clear($nameInput)
                ->type($nameInput, 'Another profile name')
                ->assertScript(
                    $saveButtonDisabled,
                    false,
                )
                ->clear($nameInput)
                ->type($nameInput, $savedName)
                ->assertScript(
                    $saveButtonDisabled,
                    true,
                )
                ->click('a[href$="/settings/appearance"]')
                ->waitForLocation('/settings/appearance')
                ->assertPathIs('/settings/appearance');
        });

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => $savedName,
        ]);
    }
}
