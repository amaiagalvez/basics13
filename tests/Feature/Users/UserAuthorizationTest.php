<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAuthorizationTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_an_account_without_permissions_is_refused_every_endpoint(): void
    {
        $this->actingAs(User::factory()->create());
        $account = User::factory()->create();
        $trashed = User::factory()->trashed()->create();

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.archived.index'))->assertForbidden();
        $this->get(route('users.trash.index'))->assertForbidden();
        $this->getJson(route('users.options'))->assertForbidden();
        $this->post(route('users.store'), $this->account())->assertForbidden();
        $this->put(route('users.update', $account->id), ['name' => 'Changed', 'email' => $account->email])->assertForbidden();
        $this->patch(route('users.archive', $account->id))->assertForbidden();
        $this->delete(route('users.destroy', $account->id))->assertForbidden();
        $this->patch(route('users.trash.restore', $trashed->id))->assertForbidden();
        $this->delete(route('users.trash.destroy', $trashed->id))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertSame($account->name, $account->refresh()->name);
        $this->assertTrue($account->active);
        $this->assertSoftDeleted($trashed);
    }

    public function test_each_action_needs_its_own_permission(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view', 'users.edit'])->create());
        $account = User::factory()->create();

        $this->get(route('users.index'))->assertOk();
        $this->put(route('users.update', $account->id), ['name' => 'Changed', 'email' => $account->email])
            ->assertRedirect(route('users.index'));
        $this->post(route('users.store'), $this->account())->assertForbidden();
        $this->delete(route('users.destroy', $account->id))->assertForbidden();

        $this->assertSame('Changed', $account->refresh()->name);
        $this->assertNotSoftDeleted($account);
    }

    public function test_the_super_admin_is_hidden_from_every_list_and_selector(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Actor account']));
        User::factory()->superAdmin()->create(['name' => 'Hidden active']);
        User::factory()->superAdmin()->archived()->create(['name' => 'Hidden archived']);
        User::factory()->superAdmin()->trashed()->create(['name' => 'Hidden trashed']);

        $this->get(route('users.index'))->assertOk()->assertSee('Actor account')->assertDontSee('Hidden active')
            ->assertViewHas('list', fn (array $list): bool => $list['tabs'][1]['count'] === 0 && $list['tabs'][2]['count'] === 0);
        $this->get(route('users.archived.index'))->assertOk()->assertDontSee('Hidden archived');
        $this->get(route('users.trash.index'))->assertOk()->assertDontSee('Hidden trashed');
        $this->getJson(route('users.options'))->assertOk()->assertJsonCount(1, 'results')->assertJsonMissing(['text' => 'Hidden active']);
    }

    public function test_nobody_else_can_act_on_the_super_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $superAdmin = User::factory()->superAdmin()->create(['name' => 'Root']);

        $this->put(route('users.update', $superAdmin->id), ['name' => 'Taken over', 'email' => $superAdmin->email])
            ->assertForbidden();
        $this->patch(route('users.archive', $superAdmin->id))->assertForbidden();
        $this->delete(route('users.destroy', $superAdmin->id))->assertForbidden();

        $superAdmin->refresh();
        $this->assertSame('Root', $superAdmin->name);
        $this->assertTrue($superAdmin->active);
        $this->assertNotSoftDeleted($superAdmin);
    }

    public function test_nobody_else_can_restore_or_delete_a_trashed_super_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $superAdmin = User::factory()->superAdmin()->trashed()->create();

        $this->patch(route('users.trash.restore', $superAdmin->id))->assertForbidden();
        $this->delete(route('users.trash.destroy', $superAdmin->id))->assertForbidden();

        $this->assertSoftDeleted($superAdmin);
    }

    public function test_the_super_admin_holds_every_account_permission_without_roles(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $account = User::factory()->create();

        $this->get(route('users.index'))->assertOk();
        $this->delete(route('users.destroy', $account->id))->assertRedirect(route('users.index'));

        $this->assertSoftDeleted($account);
    }

    /**
     * @return array<string, string>
     */
    private function account(): array
    {
        return [
            'name' => 'New account',
            'email' => 'new@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }
}
