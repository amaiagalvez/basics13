<?php

namespace Basics13\Tests\Unit\Enums;

use Basics13\Tests\TestCase;
use Basics13\Enums\SessionEvent;
use Basics13\Enums\LoginFailureReason;

class SessionEnumsTest extends TestCase
{
    public function test_every_event_has_its_own_label(): void
    {
        $this->assertSame(
            [__('Successful sign-in'), __('Failed sign-in'), __('Sign-in locked')],
            array_map(static fn (SessionEvent $event): string => $event->label(), SessionEvent::cases()),
        );
    }

    public function test_every_failure_reason_has_its_own_label(): void
    {
        $this->assertSame(
            [__('Unknown login'), __('Wrong password'), __('Inactive account'), __('Rejected while locked')],
            array_map(static fn (LoginFailureReason $reason): string => $reason->label(), LoginFailureReason::cases()),
        );
    }
}
