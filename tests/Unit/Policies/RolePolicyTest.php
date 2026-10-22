<?php

namespace Basics13\Tests\Unit\Policies;

use Basics13\Models\Role;
use Basics13\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Basics13\Policies\RolePolicy;
use Basics13\Support\Testing\PermissionsActor;
use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Attributes\DataProvider;

class RolePolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function recordAbilities(): array
    {
        return [
            'update' => ['update', 'roles.edit'],
            'delete' => ['delete', 'roles.delete'],
            'restore' => ['restore', 'roles.restore'],
            'forceDelete' => ['forceDelete', 'roles.force_delete'],
            'archive' => ['archive', 'roles.archive'],
            'activate' => ['activate', 'roles.archive'],
        ];
    }

    public function test_listing_and_creating_need_their_permission(): void
    {
        $policy = new RolePolicy;

        $this->assertTrue($policy->viewAny($this->actor(['roles.view'])));
        $this->assertFalse($policy->viewAny($this->actor([])));
        $this->assertTrue($policy->create($this->actor(['roles.create'])));
        $this->assertFalse($policy->create($this->actor(['roles.view'])));
    }

    #[DataProvider('recordAbilities')]
    public function test_each_ability_on_a_role_needs_its_permission(string $ability, string $permission): void
    {
        $policy = new RolePolicy;

        $this->assertTrue($policy->{$ability}($this->actor([$permission]), new Role));
        $this->assertFalse($policy->{$ability}($this->actor([]), new Role));
    }

    #[DataProvider('recordAbilities')]
    public function test_a_role_manager_cannot_touch_a_system_role(string $ability, string $permission): void
    {
        $this->assertFalse((new RolePolicy)->{$ability}($this->actor([$permission, 'roles.manage']), $this->systemRole()));
    }

    public function test_only_the_super_admin_edits_a_system_role_and_nobody_archives_or_deletes_it(): void
    {
        $policy = new RolePolicy;
        $superAdmin = $this->actor([], superAdmin: true);

        $this->assertTrue($policy->update($superAdmin, $this->systemRole()));

        foreach (['delete', 'restore', 'forceDelete', 'archive', 'activate'] as $ability) {
            $this->assertFalse($policy->{$ability}($superAdmin, $this->systemRole()), $ability);
        }
    }

    public function test_an_actor_without_the_roles_concern_holds_nothing(): void
    {
        $this->assertFalse((new RolePolicy)->viewAny(new GenericUser(['id' => 1])));
    }

    private function systemRole(): Role
    {
        $role = new Role;
        $role->is_system = true;

        return $role;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actor(array $permissions, bool $superAdmin = false): Authenticatable
    {
        return $superAdmin ? PermissionsActor::superAdmin() : PermissionsActor::holding($permissions);
    }
}
