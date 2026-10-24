<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\Role;
use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRoleAssignmentTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_a_role_manager_assigns_roles_and_the_assignment_records_who_did_it(): void
    {
        $actor = User::factory()->withPermissions(['users.manage', 'roles.manage'])->create();
        $account = User::factory()->create();
        $viewer = $this->role('viewer');
        $this->actingAs($actor);

        $this->put(route('users.update', $account->id), $this->edit($account, [$viewer->id]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $assigned = $account->roles()->firstOrFail();
        $this->assertSame('viewer', $assigned->slug);
        $this->assertSame($actor->id, (int) $assigned->getRelationValue('pivot')->assigned_by);
    }

    public function test_a_new_account_can_be_created_with_its_roles(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage', 'roles.manage'])->create());

        $this->post(route('users.store'), [
            'name' => 'New account',
            'email' => 'new@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'assign_roles' => '1',
            'role_ids' => [(string) $this->role('manager')->id],
        ])->assertSessionHasNoErrors();

        $account = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(['manager'], $account->roles()->pluck('slug')->all());
    }

    public function test_an_emptied_selection_removes_the_roles_and_a_form_without_selector_keeps_them(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage', 'roles.manage'])->create());
        $account = User::factory()->create();
        $account->assignRole($this->role('viewer'));

        $this->put(route('users.update', $account->id), ['name' => 'Renamed', 'email' => $account->email])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $account->roles()->count());

        $this->put(route('users.update', $account->id), ['name' => 'Renamed', 'email' => $account->email, 'assign_roles' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $account->roles()->count());
    }

    public function test_nobody_changes_their_own_roles(): void
    {
        $actor = User::factory()->withPermissions(['users.manage', 'roles.manage'])->create();
        $before = $actor->roles()->pluck('BAS_roles.id')->all();
        $this->actingAs($actor);

        $this->from(route('users.index'))
            ->put(route('users.update', $actor->id), $this->edit($actor, [$this->role('manager')->id]))
            ->assertSessionHasErrors('role_ids');

        $this->assertSame($before, $actor->roles()->pluck('BAS_roles.id')->all());
    }

    public function test_assigning_roles_needs_roles_manage(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());
        $account = User::factory()->create();

        $this->from(route('users.index'))
            ->put(route('users.update', $account->id), $this->edit($account, [$this->role('viewer')->id]))
            ->assertSessionHasErrors('role_ids');

        $this->assertSame(0, $account->roles()->count());
    }

    public function test_only_the_super_admin_assigns_a_system_role(): void
    {
        $account = User::factory()->create();
        $admin = $this->role('admin');

        $this->actingAs(User::factory()->withPermissions(['users.manage', 'roles.manage'])->create());
        $this->from(route('users.index'))
            ->put(route('users.update', $account->id), $this->edit($account, [$admin->id]))
            ->assertSessionHasErrors('role_ids.0');
        $this->assertSame(0, $account->roles()->count());

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->put(route('users.update', $account->id), $this->edit($account, [$admin->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['admin'], $account->roles()->pluck('slug')->all());
    }

    public function test_a_system_role_held_by_the_account_survives_an_edit_by_someone_else(): void
    {
        $account = User::factory()->admin()->create();
        $this->actingAs(User::factory()->withPermissions(['users.manage', 'roles.manage'])->create());

        $this->put(route('users.update', $account->id), $this->edit($account, [$this->role('viewer')->id]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['admin', 'viewer'], $account->roles()->pluck('slug')->all());
    }

    public function test_the_form_offers_system_roles_only_to_the_super_admin(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view', 'roles.manage'])->create());
        $this->get(route('users.index'))
            ->assertSee('data-test="user-role-'.$this->role('viewer')->id.'"', false)
            ->assertDontSee('data-test="user-role-'.$this->role('admin')->id.'"', false);

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->get(route('users.index'))
            ->assertSee('data-test="user-role-'.$this->role('admin')->id.'"', false);

        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());
        $this->get(route('users.index'))->assertDontSee('data-test="user-role-ids"', false);
    }

    public function test_the_list_shows_the_roles_of_each_account(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view'])->create(['name' => 'Zzz actor']));
        $account = User::factory()->create(['name' => 'Aaa account']);
        $account->assignRole($this->role('viewer'));
        $account->assignRole(Role::factory()->archived()->create(['name' => 'Old editors']));
        $plain = User::factory()->create(['name' => 'Bbb plain']);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSeeInOrder(['data-test="user-roles-'.$account->id.'"', 'Old editors', __('basics13::messages.archived_label'), 'Viewer'], false)
            ->assertSeeInOrder(['data-test="user-roles-'.$plain->id.'"', __(' ')], false);
    }

    private function role(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  list<int>  $roleIds
     * @return array<string, mixed>
     */
    private function edit(User $account, array $roleIds): array
    {
        return [
            'name' => $account->name,
            'email' => $account->email,
            'assign_roles' => '1',
            'role_ids' => array_map(strval(...), $roleIds),
        ];
    }
}
