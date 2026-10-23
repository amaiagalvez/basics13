<?php

namespace Basics13\Tests\Unit\Policies;

use Basics13\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Basics13\Policies\SessionLogPolicy;
use Basics13\Support\Testing\PermissionsActor;
use Illuminate\Contracts\Auth\Authenticatable;

class SessionLogPolicyTest extends TestCase
{
    public function test_listing_needs_sessions_view(): void
    {
        $policy = new SessionLogPolicy;

        $this->assertTrue($policy->viewAny($this->actor(['sessions.view'])));
        $this->assertFalse($policy->viewAny($this->actor([])));
        $this->assertFalse($policy->viewAny($this->actor(['users.view'])));
    }

    public function test_sessions_manage_grants_the_listing(): void
    {
        $this->assertTrue((new SessionLogPolicy)->viewAny($this->actor(['sessions.manage'])));
    }

    public function test_an_actor_without_the_roles_concern_holds_nothing(): void
    {
        $this->assertFalse((new SessionLogPolicy)->viewAny(new GenericUser(['id' => 1])));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actor(array $permissions): Authenticatable
    {
        return PermissionsActor::holding($permissions);
    }
}
