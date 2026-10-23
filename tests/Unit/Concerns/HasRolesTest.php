<?php

namespace Basics13\Tests\Unit\Concerns;

use Basics13\Models\User;
use Basics13\Tests\TestCase;

class HasRolesTest extends TestCase
{
    public function test_load_permission_slugs_returns_empty_for_unsaved_user(): void
    {
        $user = new User(['name' => 'Test', 'email' => 'test@example.test']);
        // User not saved, so exists = false

        $reflection = new \ReflectionMethod($user, 'loadPermissionSlugs');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->invoke($user));
    }
}
