<?php

namespace Basics13\Tests\Unit\Database\Factories;

use Mockery;
use Faker\Generator;
use DateTimeImmutable;
use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecordFactory;

class HasStatesTest extends TestCase
{
    public function test_get_date_range_returns_valid_dates(): void
    {
        $factory = new class extends AuditedRecordFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callgetDatesInRange(): array
            {
                return $this->getDatesInRange();
            }
        };

        $dates = $factory->callgetDatesInRange();

        $this->assertArrayHasKey('start_date', $dates);
        $this->assertArrayHasKey('end_date', $dates);
        $this->assertNotNull($dates['start_date']);
        $this->assertNotNull($dates['end_date']);
        // Both dates should be valid date strings in Y-m-d format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dates['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dates['end_date']);
    }

    public function test_get_date_range_can_return_null_when_allowed(): void
    {
        $factory = new class extends AuditedRecordFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callgetDatesInRange(bool $allowNull): array
            {
                return $this->getDatesInRange(-1, 1, $allowNull);
            }
        };

        $faker = Mockery::mock(Generator::class);
        $faker->shouldReceive('boolean')->once()->with(20)->andReturnTrue();
        $locale = config('app.faker_locale');
        $this->assertIsString($locale);
        app()->instance(Generator::class.':'.$locale, $faker);

        $dates = $factory->callgetDatesInRange(true);

        $this->assertSame(['start_date' => null, 'end_date' => null], $dates);
    }

    public function test_get_date_range_returns_ordered_dates_when_nullable_dates_are_not_selected(): void
    {
        $factory = new class extends AuditedRecordFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callgetDatesInRange(): array
            {
                return $this->getDatesInRange(-1, 1, true);
            }
        };
        $faker = Mockery::mock(Generator::class);
        $faker->shouldReceive('boolean')->once()->with(20)->andReturnFalse();
        $faker->shouldReceive('dateTimeBetween')->once()->with('-1 year', 'now')
            ->andReturn(new DateTimeImmutable('2025-10-10'));
        $faker->shouldReceive('dateTimeBetween')->once()->with('2025-10-10', '+1 year')
            ->andReturn(new DateTimeImmutable('2026-04-10'));
        $locale = config('app.faker_locale');
        $this->assertIsString($locale);
        app()->instance(Generator::class.':'.$locale, $faker);

        $dates = $factory->callgetDatesInRange();

        $this->assertSame(['start_date' => '2025-10-10', 'end_date' => '2026-04-10'], $dates);
    }
}
