<?php

namespace Basics13\Tests\Unit\Database\Factories;

use Mockery;
use Faker\Generator;
use DateTimeImmutable;
use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Tests\Fixtures\AuditedRecordFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HasStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_state_sets_active_to_false(): void
    {
        $factory = AuditedRecordFactory::new();
        $record = $factory->archived()->createOne();

        $this->assertFalse((bool) $record->active);
    }

    public function test_trashed_state_soft_deletes_the_record(): void
    {
        $factory = AuditedRecordFactory::new();
        $record = $factory->trashed()->createOne();

        $this->assertSoftDeleted($record);
    }

    public function test_get_record_returns_existing_record_when_available(): void
    {
        $existing = AuditedRecord::factory()->create();

        $record = AuditedRecordFactory::getRecord(AuditedRecord::class);

        $this->assertSame($existing->id, $record->id);
    }

    public function test_get_record_creates_new_when_none_exists(): void
    {
        $record = AuditedRecordFactory::getRecord(AuditedRecord::class);

        $this->assertDatabaseHas('audited_records', ['id' => $record->id]);
    }

    public function test_get_date_range_returns_valid_dates(): void
    {
        $factory = new class extends AuditedRecordFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callGetDateRange(): array
            {
                return $this->getDateRange();
            }
        };

        $dates = $factory->callGetDateRange();

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
            public function callGetDateRange(bool $allowNull): array
            {
                return $this->getDateRange(-1, 1, $allowNull);
            }
        };

        $faker = Mockery::mock(Generator::class);
        $faker->shouldReceive('boolean')->once()->with(20)->andReturnTrue();
        $locale = config('app.faker_locale');
        $this->assertIsString($locale);
        app()->instance(Generator::class.':'.$locale, $faker);

        $dates = $factory->callGetDateRange(true);

        $this->assertSame(['start_date' => null, 'end_date' => null], $dates);
    }

    public function test_get_date_range_returns_ordered_dates_when_nullable_dates_are_not_selected(): void
    {
        $factory = new class extends AuditedRecordFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callGetDateRange(): array
            {
                return $this->getDateRange(-1, 1, true);
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

        $dates = $factory->callGetDateRange();

        $this->assertSame(['start_date' => '2025-10-10', 'end_date' => '2026-04-10'], $dates);
    }

    public function test_get_record_static_method_works_directly(): void
    {
        // Test the static getRecord method directly on the factory (where the trait method is available)
        $record = AuditedRecordFactory::getRecord(AuditedRecord::class);

        $this->assertInstanceOf(AuditedRecord::class, $record);
        $this->assertDatabaseHas('audited_records', ['id' => $record->id]);
    }
}
