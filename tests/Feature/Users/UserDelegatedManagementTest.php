<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * `users.view_own` and `users.manage_own` over the accounts sharing an active type the actor
 * administers, and nothing beyond them.
 */
class UserDelegatedManagementTest extends UserTestCase
{
    use RefreshDatabase;

    private UserType $typeA;

    private UserType $typeB;

    private User $member;

    private User $outsider;

    private User $peerAdmin;

    private User $globalManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeA = UserType::factory()->create();
        $this->typeB = UserType::factory()->create();
        $this->member = $this->inType(User::factory()->create(['name' => 'Member of A']), $this->typeA);
        $this->outsider = $this->inType(User::factory()->create(['name' => 'Outsider in B']), $this->typeB);
        $this->peerAdmin = $this->inType(User::factory()->create(['name' => 'Peer admin of A']), $this->typeA, admin: true);
        $this->globalManager = $this->inType(
            User::factory()->withPermissions(['users.edit'])->create(['name' => 'Global manager in A']),
            $this->typeA,
        );
    }

    public function test_the_lists_and_the_selector_show_only_the_managed_accounts(): void
    {
        $this->actingAs($this->typeAdmin(['users.view_own']));
        $this->inType(User::factory()->archived()->create(['name' => 'Archived member']), $this->typeA);
        $trashed = $this->inType(User::factory()->create(['name' => 'Trashed member']), $this->typeA);
        $trashed->delete();
        $trashedOutsider = $this->inType(User::factory()->create(['name' => 'Trashed outsider']), $this->typeB);
        $trashedOutsider->delete();

        $this->get(route('users.index'))->assertOk()
            ->assertSee('Member of A')
            ->assertDontSee('Outsider in B')
            ->assertDontSee('Peer admin of A')
            ->assertDontSee('Global manager in A')
            ->assertDontSee('Type admin');
        $this->get(route('users.archived.index'))->assertOk()->assertSee('Archived member');
        $this->get(route('users.trash.index'))->assertOk()
            ->assertSee('Trashed member')
            ->assertDontSee('Trashed outsider');

        $this->getJson(route('users.options'))->assertOk()
            ->assertJsonPath('results', [['id' => $this->member->id, 'text' => 'Member of A']]);
    }

    public function test_the_permissions_summary_shows_only_the_managed_accounts(): void
    {
        $this->actingAs($this->typeAdmin(['users.view_own', 'roles.view']));

        $this->get(route('users.permissions-summary.index'))->assertOk()
            ->assertSee('Member of A')
            ->assertDontSee('Outsider in B');
        $this->assertStringNotContainsString(
            'Outsider in B',
            $this->get(route('users.permissions-summary.export'))->streamedContent(),
        );
    }

    public function test_view_own_does_not_manage(): void
    {
        $this->actingAs($this->typeAdmin(['users.view_own']));

        $this->put(route('users.update', $this->member), $this->edit($this->member))->assertForbidden();
        $this->patch(route('users.archive', $this->member))->assertForbidden();
        $this->delete(route('users.destroy', $this->member))->assertForbidden();
        $this->post(route('users.store'), $this->newAccount([$this->typeA->id]))->assertForbidden();
    }

    public function test_manage_own_acts_on_its_accounts_and_only_on_them(): void
    {
        $this->actingAs($this->typeAdmin(['users.manage_own']));

        $this->get(route('users.index'))->assertOk()->assertSee('Member of A');
        $this->put(route('users.update', $this->member), $this->edit($this->member, ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $this->member->refresh()->name);

        foreach ([$this->outsider, $this->peerAdmin, $this->globalManager] as $account) {
            $this->put(route('users.update', $account), $this->edit($account))->assertForbidden();
            $this->patch(route('users.archive', $account))->assertForbidden();
            $this->delete(route('users.destroy', $account))->assertForbidden();
        }

        $this->patch(route('users.archive', $this->member))->assertSessionHasNoErrors();
        $this->patch(route('users.archived.activate', $this->member))->assertSessionHasNoErrors();
        $this->delete(route('users.destroy', $this->member))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($this->member);
        $this->patch(route('users.trash.restore', $this->member->id))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($this->member);

        $this->outsider->delete();
        $this->patch(route('users.trash.restore', $this->outsider->id))->assertForbidden();
        $this->delete(route('users.trash.destroy', $this->outsider->id))->assertForbidden();
    }

    public function test_the_actor_is_not_one_of_its_accounts_and_never_impersonates(): void
    {
        $actor = $this->typeAdmin(['users.manage_own']);
        $this->actingAs($actor);

        $this->patch(route('users.archive', $actor))->assertForbidden();
        $this->post(route('users.impersonate', $this->member))->assertForbidden();
    }

    public function test_an_archived_type_grants_nothing(): void
    {
        $this->actingAs($this->typeAdmin(['users.manage_own']));
        $this->typeA->forceFill(['active' => false])->save();

        $this->get(route('users.index'))->assertOk()->assertDontSee('Member of A');
        $this->put(route('users.update', $this->member), $this->edit($this->member))->assertForbidden();
    }

    public function test_a_global_viewer_is_not_narrowed(): void
    {
        $this->actingAs($this->typeAdmin(['users.view', 'users.manage_own']));

        $this->get(route('users.index'))->assertOk()->assertSee('Outsider in B')->assertSee('Peer admin of A');
    }

    public function test_a_type_admin_grants_only_roles_within_its_own_permissions(): void
    {
        $this->actingAs($this->typeAdmin(['users.manage_own', 'roles.view']));
        $within = Role::factory()->withPermissions(['roles.view'])->create();
        $beyond = Role::factory()->withPermissions(['users.manage'])->create();
        $kept = Role::factory()->withPermissions(['sessions.view'])->create();
        $this->member->assignRole($kept);

        $this->get(route('users.index'))->assertOk()
            ->assertSee('data-test="user-role-'.$within->id.'"', false)
            ->assertDontSee('data-test="user-role-'.$beyond->id.'"', false);

        $this->put(route('users.update', $this->member), $this->edit($this->member, roles: [$beyond->id]))
            ->assertSessionHasErrors('role_ids.0');

        $this->put(route('users.update', $this->member), $this->edit($this->member, roles: [$within->id]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$within->id, $kept->id], $this->member->roles()->pluck('BAS_roles.id')->all());
    }

    public function test_a_type_admin_touches_only_its_own_types_and_never_the_admin_flag(): void
    {
        $this->actingAs($this->typeAdmin(['users.manage_own']));
        $this->member->userTypes()->attach($this->typeB);

        $this->put(route('users.update', $this->member), $this->edit($this->member, types: [$this->typeB->id]))
            ->assertSessionHasErrors('user_type_ids.0');
        $this->put(route('users.update', $this->member), $this->edit($this->member, types: []))
            ->assertSessionHasErrors('user_type_ids');
        $this->put(route('users.update', $this->member), [
            ...$this->edit($this->member),
            'admin_type_ids' => [(string) $this->typeA->id],
        ])->assertSessionHasErrors('admin_type_ids');

        $this->put(route('users.update', $this->member), $this->edit($this->member))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(
            [$this->typeA->id, $this->typeB->id],
            $this->member->userTypes()->pluck('BAS_user_types.id')->all(),
        );
    }

    public function test_a_type_admin_creates_accounts_only_inside_its_types(): void
    {
        $this->actingAs($this->typeAdmin(['users.manage_own']));

        $this->post(route('users.store'), $this->newAccount([]))->assertSessionHasErrors('user_type_ids');
        $this->post(route('users.store'), $this->newAccount([$this->typeB->id]))->assertSessionHasErrors('user_type_ids.0');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);

        $this->post(route('users.store'), $this->newAccount([$this->typeA->id]))->assertSessionHasNoErrors();
        $account = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame([$this->typeA->id], $account->userTypes()->pluck('BAS_user_types.id')->all());
    }

    public function test_a_global_editor_assigns_types_and_the_admin_flag(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());

        $this->put(route('users.update', $this->outsider), [
            ...$this->edit($this->outsider, types: [$this->typeA->id, $this->typeB->id]),
            'admin_type_ids' => [(string) $this->typeA->id],
        ])->assertSessionHasNoErrors();

        $types = $this->outsider->userTypes()->get()->mapWithKeys(
            static fn (UserType $type): array => [$type->id => (bool) $type->getRelationValue('pivot')->is_admin],
        )->all();
        $this->assertSame([$this->typeA->id => true, $this->typeB->id => false], $types);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function typeAdmin(array $permissions): User
    {
        return $this->inType(
            User::factory()->withPermissions($permissions)->create(['name' => 'Type admin']),
            $this->typeA,
            admin: true,
        );
    }

    private function inType(User $user, UserType $type, bool $admin = false): User
    {
        $user->userTypes()->attach($type, ['is_admin' => $admin]);

        return $user;
    }

    /**
     * @param  array<string, string>  $overrides
     * @param  list<int>|null  $roles
     * @param  list<int>|null  $types
     * @return array<string, mixed>
     */
    private function edit(User $user, array $overrides = [], ?array $roles = null, ?array $types = null): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            ...($roles !== null ? ['assign_roles' => '1', 'role_ids' => array_map(strval(...), $roles)] : []),
            'assign_types' => '1',
            'user_type_ids' => array_map(strval(...), $types ?? [$this->typeA->id]),
            ...$overrides,
        ];
    }

    /**
     * @param  list<int>  $types
     * @return array<string, mixed>
     */
    private function newAccount(array $types): array
    {
        return [
            'name' => 'New account',
            'email' => 'new@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'assign_types' => '1',
            'user_type_ids' => array_map(strval(...), $types),
        ];
    }
}
