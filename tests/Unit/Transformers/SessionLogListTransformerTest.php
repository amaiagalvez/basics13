<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Support\Carbon;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Basics13\Enums\LoginFailureReason;
use Basics13\Transformers\SessionLogListTransformer;

class SessionLogListTransformerTest extends TestCase
{
    public function test_a_failure_carries_its_reason_and_its_account(): void
    {
        $user = (new User)->forceFill(['id' => 7, 'name' => 'Ane', 'deleted_at' => Carbon::parse('2026-10-02 08:00:00')]);
        $session = (new SessionLog)->forceFill([
            'id' => 3,
            'event' => SessionEvent::Failed,
            'failure_reason' => LoginFailureReason::WrongPassword,
            'login' => 'ane@example.com',
            'ip' => '10.0.0.1',
            'user_agent' => 'Firefox',
            'created_at' => Carbon::parse('2026-10-01 09:30:00'),
        ])->setRelation('user', $user);

        $row = (new SessionLogListTransformer)->row($session);

        $this->assertSame(3, $row['id']);
        $this->assertSame('failed', $row['event']);
        $this->assertSame(__('Failed sign-in'), $row['eventLabel']);
        $this->assertSame(__('Wrong password'), $row['reason']);
        $this->assertSame('Ane', $row['user']);
        $this->assertTrue($row['userTrashed']);
        $this->assertSame('ane@example.com', $row['login']);
        $this->assertSame(Carbon::parse('2026-10-01 09:30:00')->toIso8601String(), $row['createdAt']);
        $this->assertNull($row['loggedOutAt']);
        $this->assertNull($row['lockoutMinutes']);
    }

    public function test_an_attempt_without_an_account_and_a_lockout(): void
    {
        $session = (new SessionLog)->forceFill([
            'id' => 4,
            'event' => SessionEvent::Lockout,
            'login' => 'ghost@example.com',
            'ip' => '10.0.0.2',
            'user_agent' => '',
            'lockout_minutes' => 15,
        ])->setRelation('user', null);

        $row = (new SessionLogListTransformer)->row($session);

        $this->assertNull($row['user']);
        $this->assertFalse($row['userTrashed']);
        $this->assertNull($row['reason']);
        $this->assertSame(15, $row['lockoutMinutes']);
        $this->assertSame(__('Sign-in locked'), $row['eventLabel']);
    }
}
