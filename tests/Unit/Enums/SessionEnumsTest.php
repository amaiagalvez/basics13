<?php

namespace Basics13\Tests\Unit\Enums;

use Basics13\Tests\TestCase;
use Basics13\Enums\DeviceType;
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

    public function test_the_device_type_is_read_from_the_user_agent(): void
    {
        $agents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148' => DeviceType::Mobile,
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/124.0 Mobile Safari/537.36' => DeviceType::Mobile,
            'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148' => DeviceType::Tablet,
            'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/124.0 Safari/537.36' => DeviceType::Tablet,
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36' => DeviceType::Desktop,
            '' => DeviceType::Desktop,
        ];

        foreach ($agents as $agent => $device) {
            $this->assertSame($device, DeviceType::fromUserAgent($agent), $agent);
        }
    }

    public function test_every_device_type_has_its_own_label(): void
    {
        $this->assertSame(
            [__('Desktop'), __('Mobile'), __('Tablet')],
            array_map(static fn (DeviceType $device): string => $device->label(), DeviceType::cases()),
        );
    }
}
