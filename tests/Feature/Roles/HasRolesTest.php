<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Illuminate\Support\Facades\DB;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HasRolesTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_manage_grants_every_action_of_its_module_only(): void
    {
        $user = User::factory()->withPermissions(['roles.manage'])->create();

        $this->assertTrue($user->hasPermissionTo('roles.edit'));
        $this->assertTrue($user->hasPermissionTo('roles.force_delete'));
        $this->assertFalse($user->hasPermissionTo('users.view'));
    }

    public function test_the_permissions_of_every_role_add_up(): void
    {
        $user = User::factory()->withPermissions(['users.view'])->create();
        $user->assignRole(Role::factory()->withPermissions(['roles.edit'])->create());

        $this->assertTrue($user->hasPermissionTo('users.view'));
        $this->assertTrue($user->hasPermissionTo('roles.edit'));
        $this->assertFalse($user->hasPermissionTo('users.edit'));
    }

    public function test_an_archived_or_trashed_role_grants_nothing(): void
    {
        $archived = Role::factory()->archived()->withPermissions(['users.edit'])->create();
        $trashed = Role::factory()->withPermissions(['roles.edit'])->create();
        $user = User::factory()->create();
        $user->assignRole($archived);
        $user->assignRole($trashed);
        // The guard refuses to trash a role an account holds, so the row is trashed directly.
        DB::table('roles')->where('id', $trashed->id)->update(['deleted_at' => now()]);
        $user->forgetPermissions();

        $this->assertFalse($user->hasPermissionTo('users.edit'));
        $this->assertFalse($user->hasPermissionTo('roles.edit'));
    }

    public function test_a_role_change_is_seen_once_the_permissions_are_forgotten(): void
    {
        $role = Role::factory()->withPermissions(['users.edit'])->create();
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->assertTrue($user->hasPermissionTo('users.edit'));

        $role->active = false;
        $role->save();

        $this->assertTrue($user->hasPermissionTo('users.edit'), 'The slugs stay loaded for the request.');
        $this->assertFalse(User::query()->whereKey($user->id)->firstOrFail()->hasPermissionTo('users.edit'));

        $user->forgetPermissions();
        $this->assertFalse($user->hasPermissionTo('users.edit'));
    }

    public function test_the_super_admin_holds_every_permission_without_a_query(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertTrue($superAdmin->hasPermissionTo('users.force_delete'));
        $this->assertTrue($superAdmin->hasPermissionTo('anything.view'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_many_checks_cost_a_single_query(): void
    {
        $user = User::factory()->withPermissions(['users.view', 'roles.manage'])->create();
        $user->forgetPermissions();

        DB::enableQueryLog();
        DB::flushQueryLog();

        foreach (['users.view', 'users.edit', 'roles.view', 'roles.delete', 'users.view'] as $permission) {
            $user->hasPermissionTo($permission);
        }

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_assigning_records_who_assigned_and_keeps_existing_assignments(): void
    {
        $assigner = User::factory()->create();
        $other = User::factory()->create();
        $kept = Role::factory()->create();
        $added = Role::factory()->create();
        $dropped = Role::factory()->create();
        $user = User::factory()->create();
        $user->syncRoles([$kept->id, $dropped->id], $assigner->id);

        $user->syncRoles([$kept->id, $added->id], $other->id);

        $assignedBy = $user->roles()->pluck('role_user.assigned_by', 'roles.id')->all();
        $this->assertEqualsCanonicalizing([$kept->id, $added->id], array_keys($assignedBy));
        $this->assertSame($assigner->id, (int) $assignedBy[$kept->id]);
        $this->assertSame($other->id, (int) $assignedBy[$added->id]);
        $this->assertNotNull($user->roles()->firstOrFail()->getRelationValue('pivot')->created_at);

        $user->removeRole($kept);
        $this->assertSame([$added->id], $user->roles()->pluck('roles.id')->all());
    }

    public function test_the_super_admin_never_receives_a_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->expectException(\LogicException::class);

        $superAdmin->assignRole(Role::factory()->create());
    }
}
