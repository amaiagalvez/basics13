<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTrashTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_trash_list_searches_deleted_accounts_without_showing_live_records(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Zzz actor']));
        User::factory()->trashed()->create(['name' => 'Matching deleted account']);
        User::factory()->trashed()->create(['name' => 'Other deleted account']);
        User::factory()->create(['name' => 'Matching live account']);

        $this->get(route('users.trash.index', ['search' => 'Matching']))
            ->assertOk()
            ->assertSee('Matching deleted account')
            ->assertDontSee('Other deleted account')
            ->assertDontSee('Matching live account');
    }

    public function test_an_account_can_be_restored_and_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $account = User::factory()->create();
        $account->delete();

        $this->patch(route('users.trash.restore', $account->getKey()))
            ->assertRedirect(route('users.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($account);

        $account->delete();

        $this->delete(route('users.trash.destroy', $account->getKey()))
            ->assertRedirect(route('users.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertModelMissing($account);
    }

    public function test_a_restored_account_returns_to_the_active_list(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Zzz actor']));
        $account = User::factory()->trashed()->create(['name' => 'Restored account']);

        $this->get(route('users.trash.index'))
            ->assertOk()
            ->assertSee('Restored account');

        $this->patch(route('users.trash.restore', $account->getKey()))
            ->assertRedirect(route('users.trash.index'));

        $this->assertNotSoftDeleted($account);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Restored account');
    }
}
