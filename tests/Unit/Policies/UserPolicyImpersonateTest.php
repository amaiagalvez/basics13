<?php

namespace Basics13\Tests\Unit\Policies;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Basics13\Policies\UserPolicy;
use Basics13\Support\Testing\PermissionsActor;

class UserPolicyImpersonateTest extends TestCase
{
    public function test_superadmin_can_impersonate_normal_user(): void
    {
        $policy = new UserPolicy;
        $superAdmin = PermissionsActor::superAdmin(1);
        $target = User::factory()->make(['id' => 2, 'is_super_admin' => false, 'active' => true]);

        $this->assertTrue($policy->impersonate($superAdmin, $target));
    }

    public function test_superadmin_cannot_impersonate_self(): void
    {
        $policy = new UserPolicy;
        $superAdmin = PermissionsActor::superAdmin(1);
        $target = User::factory()->make(['id' => 1, 'is_super_admin' => true, 'active' => true]);

        $this->assertFalse($policy->impersonate($superAdmin, $target));
    }

    public function test_superadmin_cannot_impersonate_another_superadmin(): void
    {
        $policy = new UserPolicy;
        $superAdmin = PermissionsActor::superAdmin(1);
        $target = User::factory()->make(['id' => 3, 'is_super_admin' => true, 'active' => true]);

        $this->assertFalse($policy->impersonate($superAdmin, $target));
    }

    public function test_non_superadmin_cannot_impersonate(): void
    {
        $policy = new UserPolicy;
        $actor = PermissionsActor::holding(['users.manage', 'users.impersonate']);
        $target = User::factory()->make(['id' => 2, 'is_super_admin' => false, 'active' => true]);

        $this->assertFalse($policy->impersonate($actor, $target));
    }

    public function test_actor_without_hasroles_concern_cannot_impersonate(): void
    {
        $policy = new UserPolicy;
        $actor = new GenericUser(['id' => 1]);
        $target = User::factory()->make(['id' => 2, 'is_super_admin' => false, 'active' => true]);

        $this->assertFalse($policy->impersonate($actor, $target));
    }
}
