<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserArchivedTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_archived_list_shows_only_inactive_accounts(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Zzz actor']));
        User::factory()->archived()->create(['name' => 'Archived account']);

        $this->get(route('users.archived.index'))
            ->assertOk()
            ->assertSee('Archived account')
            ->assertDontSee('Zzz actor');
    }

    public function test_an_account_can_be_archived_and_activated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $account = User::factory()->create();

        $this->patch(route('users.archive', $account->getKey()))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status', __('basics13::messages.archived'));
        $this->assertFalse($account->refresh()->active);

        $this->patch(route('users.archived.activate', $account->getKey()))
            ->assertRedirect(route('users.archived.index'))
            ->assertSessionHas('status', __('basics13::messages.activated'));
        $this->assertTrue($account->refresh()->active);
    }
}
