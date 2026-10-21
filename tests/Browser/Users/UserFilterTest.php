<?php

namespace Basics13\Tests\Browser\Users;

use Basics13\Models\User;
use Laravel\Dusk\Browser;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UserFilterTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_advanced_search_filters_users_and_removes_the_filter(): void
    {
        $user = $this->user();
        $client = User::factory()->create(['name' => 'Client account', 'email' => 'client@client.test']);
        $other = User::factory()->create(['name' => 'Other account', 'email' => 'other@example.test']);

        $this->browse(function (Browser $browser) use ($user, $client, $other): void {
            $browser->loginAs($user)
                ->visit('/users')
                ->click('[data-test="user-advanced-search"]')
                ->waitFor('dialog[open] #advanced-search-heading')
                ->assertSeeIn('dialog[open]', __('Email'))
                ->assertSeeIn('dialog[open]', __('basics13::messages.created_at'))
                ->assertDontSeeIn('dialog[open]', 'basics13::messages.')
                ->assertScript('document.querySelector(\'[data-test="active-advanced-search-operator"]\').value', '')
                ->select('[data-test="email-advanced-search-operator"]', 'ends_with')
                ->type('dialog[open] input[wire\\:model="filters.email.value"]', 'client.test')
                ->press(__('basics13::messages.apply_filters'))
                ->waitFor('[data-test="user-filter-remove-email"]')
                ->assertSee($client->name)
                ->assertDontSee($other->name)
                ->click('[data-test="user-filter-remove-email"]')
                ->waitUntilMissing('[data-test="user-filter-badges"]')
                ->assertSee($other->name);

            $this->assertSame([], array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                fn (array $entry): bool => $entry['level'] === 'SEVERE',
            )));
        });
    }
}
