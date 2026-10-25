<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Models\PersonalEvent;
use Illuminate\Support\Facades\Gate;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RolelessAccountTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_an_account_without_a_role_is_refused_every_ability(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->denies('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($user)->denies('create', PersonalEvent::class));
    }

    public function test_an_account_whose_only_role_is_archived_is_refused_every_ability(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::factory()->archived()->withPermissions(['roles.view'])->create());

        $this->assertTrue(Gate::forUser($user)->denies('viewAny', Role::class));
    }

    public function test_a_role_holder_keeps_the_abilities_of_its_permissions(): void
    {
        $user = User::factory()->withPermissions(['roles.view'])->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', PersonalEvent::class));
    }

    public function test_the_super_admin_without_a_role_keeps_its_access(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Role::class));
    }
}
