<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleCrudTest extends RoleTestCase
{
    use RefreshDatabase;

    public function test_a_role_is_created_with_its_permissions_updated_and_trashed(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());

        $this->post(route('roles.store'), [
            'name' => 'Editors',
            'slug' => 'editors',
            'description' => 'They edit accounts',
            'permission_ids' => [(string) $this->permission('users.view'), (string) $this->permission('users.edit')],
        ])->assertRedirect(route('roles.index'))->assertSessionHasNoErrors();

        $role = Role::query()->where('slug', 'editors')->firstOrFail();
        $this->assertEqualsCanonicalizing(['users.view', 'users.edit'], $role->permissions()->pluck('slug')->all());

        $this->get(route('roles.index'))
            ->assertOk()
            ->assertSee('data-test="role-name-'.$role->id.'"', false)
            ->assertViewHas('list', fn (array $list): bool => array_column($list['rows'], 'permissionsCount', 'id')[$role->id] === 2);

        $this->put(route('roles.update', $role), [
            'name' => 'Account editors',
            'slug' => 'account-editors',
            'permission_ids' => [(string) $this->permission('users.view')],
        ])->assertRedirect(route('roles.index'))->assertSessionHasNoErrors();

        $role->refresh();
        $this->assertSame('Account editors', $role->name);
        $this->assertSame(['users.view'], $role->permissions()->pluck('slug')->all());

        $this->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));
        $this->assertSoftDeleted($role);
    }

    public function test_the_slug_is_derived_from_the_name_when_left_empty(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());

        $this->post(route('roles.store'), ['name' => 'Área técnica', 'slug' => ''])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('BAS_roles', ['name' => 'Área técnica', 'slug' => 'area-tecnica']);
    }

    public function test_the_slug_of_an_archived_role_is_taken(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        Role::factory()->archived()->create(['name' => 'Old editors', 'slug' => 'editors']);

        $this->from(route('roles.index'))
            ->post(route('roles.store'), ['name' => 'Editors', 'slug' => 'editors'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Role::query()->where('slug', 'editors')->count());
    }

    public function test_the_system_and_active_flags_of_a_payload_are_ignored(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());

        $this->post(route('roles.store'), [
            'name' => 'Editors',
            'slug' => 'editors',
            'is_system' => '1',
            'active' => '0',
        ])->assertSessionHasNoErrors();

        $role = Role::query()->where('slug', 'editors')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->active);
    }

    public function test_a_role_held_by_an_account_is_archived_instead_of_trashed(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $role = Role::factory()->create();
        User::factory()->trashed()->create()->assignRole($role);

        $this->get(route('roles.index'))
            ->assertSee('data-test="role-archive-'.$role->id.'"', false)
            ->assertDontSee('data-test="role-delete-'.$role->id.'"', false);

        $this->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_delete_related'));
        $this->assertNotSoftDeleted($role);

        $this->patch(route('roles.archive', $role))->assertRedirect(route('roles.index'));
        $this->assertFalse($role->refresh()->active);
    }

    public function test_a_system_role_is_edited_only_by_the_super_admin_and_never_archived_or_trashed(): void
    {
        $admin = Role::query()->where('slug', 'admin')->firstOrFail();

        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $this->get(route('roles.index'))
            ->assertSee('data-test="role-edit-blocked-'.$admin->id.'"', false)
            ->assertSee('data-test="role-delete-blocked-'.$admin->id.'"', false);
        $this->put(route('roles.update', $admin), ['name' => 'Renamed', 'slug' => 'admin'])->assertForbidden();
        $this->patch(route('roles.archive', $admin))->assertForbidden();
        $this->delete(route('roles.destroy', $admin))->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->from(route('roles.index'))
            ->put(route('roles.update', $admin), ['name' => 'Administrators', 'slug' => 'administrators'])
            ->assertSessionHasErrors('slug');
        $this->put(route('roles.update', $admin), ['name' => 'Administrators', 'slug' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->patch(route('roles.archive', $admin))->assertForbidden();

        $admin->refresh();
        $this->assertSame('Administrators', $admin->name);
        $this->assertTrue($admin->active);
    }

    public function test_every_endpoint_needs_its_permission(): void
    {
        $role = Role::factory()->create();
        $trashed = Role::factory()->trashed()->create();
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());

        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('roles.archived.index'))->assertForbidden();
        $this->get(route('roles.trash.index'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Editors', 'slug' => 'editors'])->assertForbidden();
        $this->put(route('roles.update', $role), ['name' => 'Renamed', 'slug' => $role->slug])->assertForbidden();
        $this->patch(route('roles.archive', $role))->assertForbidden();
        $this->delete(route('roles.destroy', $role))->assertForbidden();
        $this->patch(route('roles.trash.restore', $trashed->id))->assertForbidden();
        $this->delete(route('roles.trash.destroy', $trashed->id))->assertForbidden();

        $this->assertDatabaseMissing('BAS_roles', ['slug' => 'editors']);
        $this->assertSoftDeleted($trashed);
    }

    public function test_archived_and_trashed_roles_have_their_own_lists(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        Role::factory()->create(['name' => 'Live role']);
        $archived = Role::factory()->archived()->create(['name' => 'Archived role']);
        Role::factory()->trashed()->create(['name' => 'Trashed role']);

        $this->get(route('roles.archived.index'))
            ->assertOk()
            ->assertSee('Archived role')
            ->assertDontSee('Live role')
            ->assertSee('data-test="role-activate-'.$archived->id.'"', false);
        $this->get(route('roles.trash.index', ['search' => 'Trashed']))
            ->assertOk()
            ->assertSee('Trashed role')
            ->assertDontSee('Archived role');

        $this->patch(route('roles.archived.activate', $archived))->assertRedirect(route('roles.archived.index'));
        $this->assertTrue($archived->refresh()->active);
    }

    public function test_a_viewer_lists_roles_without_the_create_action(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());

        $this->get(route('roles.index'))
            ->assertOk()
            ->assertDontSee('data-test="role-create-button"', false);
    }

    public function test_a_trashed_role_can_be_restored_unless_its_slug_is_taken_and_then_deleted_for_good(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $trashed = Role::factory()->trashed()->create(['name' => 'Editors', 'slug' => 'editors']);
        $live = Role::factory()->create(['name' => 'Other editors', 'slug' => 'editors']);

        $this->patch(route('roles.trash.restore', $trashed->id))
            ->assertRedirect(route('roles.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'));
        $this->assertSoftDeleted($trashed);

        $live->delete();
        $this->patch(route('roles.trash.restore', $trashed->id))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($trashed);

        $trashed->delete();
        $this->delete(route('roles.trash.destroy', $trashed->id))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertModelMissing($trashed);
    }

    public function test_creating_with_the_name_of_a_trashed_role_offers_to_restore_it(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $trashed = Role::factory()->trashed()->create(['name' => 'Editors', 'slug' => 'editors']);

        $this->post(route('roles.store'), ['name' => 'Editors', 'slug' => 'editors'])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('deleted_role_conflict', ['id' => $trashed->id, 'name' => 'Editors']);

        $this->post(route('roles.store'), ['name' => 'Editors', 'slug' => 'editors', 'reuse_deleted_name' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Role::withTrashed()->where('slug', 'editors')->count());
    }

    public function test_store_converts_a_concurrent_duplicate_slug_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $this->createDuplicateAfterValidation('editors');

        $this->postJson(route('roles.store'), ['name' => 'Editors', 'slug' => 'editors'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame(1, Role::query()->where('slug', 'editors')->count());
    }

    public function test_store_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $this->createDuplicateAfterValidation('Editors', 'name');

        $this->postJson(route('roles.store'), ['name' => 'Editors', 'slug' => 'editors'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Role::query()->where('name', 'Editors')->count());
    }

    public function test_update_converts_a_concurrent_duplicate_slug_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $role = Role::factory()->create(['name' => 'Original', 'slug' => 'original']);
        $this->createDuplicateAfterValidation('editors', 'slug');

        $this->putJson(route('roles.update', $role), ['name' => 'Editors', 'slug' => 'editors'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame(1, Role::query()->where('slug', 'editors')->count());
    }

    public function test_update_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $role = Role::factory()->create(['name' => 'Original', 'slug' => 'original']);
        $this->createDuplicateAfterValidation('Editors', 'name');

        $this->putJson(route('roles.update', $role), ['name' => 'Editors', 'slug' => 'editors'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Role::query()->where('name', 'Editors')->count());
    }

    private function permission(string $slug): int
    {
        return (int) Permission::query()->where('slug', $slug)->value('id');
    }

    /**
     * Slips a competing role with the same slug in between the unique rule and the write.
     */
    private function createDuplicateAfterValidation(string $value, string $field = 'slug'): void
    {
        $created = false;

        DB::listen(static function (QueryExecuted $query) use ($value, $field, &$created): void {
            if ($created || ! str_starts_with(strtolower(ltrim($query->sql)), 'select') || ! in_array($value, $query->bindings, true)) {
                return;
            }

            $created = true;
            if ($field === 'slug') {
                Role::factory()->create(['name' => 'Competitor', 'slug' => $value]);
            } else {
                Role::factory()->create(['name' => $value, 'slug' => 'competitor-'.$value]);
            }
        });
    }
}
