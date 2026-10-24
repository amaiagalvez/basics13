<?php

namespace Basics13\Tests\Unit\Models;

use Carbon\CarbonImmutable;
use Basics13\Tests\TestCase;
use Basics13\Models\PersonalEvent;

class PersonalEventTest extends TestCase
{
    public function test_the_dates_are_cast_and_the_owner_is_not_mass_assigned(): void
    {
        $event = new PersonalEvent(['title' => 'Dentista', 'start_date' => '2028-02-29', 'user_id' => 7]);

        $this->assertInstanceOf(CarbonImmutable::class, $event->start_date);
        $this->assertSame('2028-02-29', $event->start_date->toDateString());
        $this->assertNull($event->end_date);
        $this->assertArrayNotHasKey('user_id', $event->getAttributes());
    }

    public function test_an_event_without_end_date_lasts_its_only_day(): void
    {
        $oneDay = new PersonalEvent(['start_date' => '2026-06-12']);
        $range = new PersonalEvent(['start_date' => '2026-06-12', 'end_date' => '2026-06-15']);

        $this->assertSame('2026-06-12', $oneDay->lastDay()->toDateString());
        $this->assertSame('2026-06-15', $range->lastDay()->toDateString());
    }
}
