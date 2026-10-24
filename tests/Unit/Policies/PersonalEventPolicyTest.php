<?php

namespace Basics13\Tests\Unit\Policies;

use Basics13\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Basics13\Models\PersonalEvent;
use Basics13\Policies\PersonalEventPolicy;

class PersonalEventPolicyTest extends TestCase
{
    public function test_any_user_creates_and_only_the_owner_updates_or_deletes(): void
    {
        $policy = new PersonalEventPolicy;
        $event = new PersonalEvent;
        $event->user_id = 1;
        $owner = new GenericUser(['id' => 1]);
        $other = new GenericUser(['id' => 2]);

        $this->assertTrue($policy->create($other));
        $this->assertTrue($policy->update($owner, $event));
        $this->assertTrue($policy->delete($owner, $event));
        $this->assertFalse($policy->update($other, $event));
        $this->assertFalse($policy->delete($other, $event));
    }
}
