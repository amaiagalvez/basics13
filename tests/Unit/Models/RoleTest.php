<?php

namespace Basics13\Tests\Unit\Models;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_system_and_active_flags_are_not_mass_assignable(): void
    {
        $role = new Role([
            'name' => 'Editors',
            'slug' => 'editors',
            'is_system' => true,
            'active' => false,
        ]);

        $this->assertSame('Editors', $role->name);
        $this->assertSame('editors', $role->slug);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->active);
    }

    public function test_the_super_admin_flag_is_not_mass_assignable(): void
    {
        $user = new User(['name' => 'Ane', 'is_super_admin' => true]);

        $this->assertFalse($user->is_super_admin);
        $this->assertFalse($user->isSuperAdmin());
    }
}
