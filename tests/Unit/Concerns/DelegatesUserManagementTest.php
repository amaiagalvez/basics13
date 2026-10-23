<?php

namespace Basics13\Tests\Unit\Concerns;

use Basics13\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Basics13\Concerns\DelegatesUserManagement;

/**
 * Only an account that can answer for its permissions may hand roles out as a type admin.
 */
class DelegatesUserManagementTest extends TestCase
{
    public function test_an_actor_without_permission_checks_can_grant_no_role(): void
    {
        $delegate = new class
        {
            use DelegatesUserManagement;

            /**
             * @return list<int>
             */
            public function grantable(GenericUser $actor): array
            {
                return $this->grantableRoleIds($actor);
            }
        };

        $this->assertSame([], $delegate->grantable(new GenericUser(['id' => 1])));
    }
}
