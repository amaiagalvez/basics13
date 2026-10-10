<?php

namespace Basics13\Tests\Unit\Database\Factories;

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

        // Test with allowNull = false (should never return null)
        $dates = $factory->callGetDateRange(false);
        $this->assertNotNull($dates['start_date']);
        $this->assertNotNull($dates['end_date']);

        // Test with allowNull = true - just ensure it runs without error
        // The actual null return is probabilistic (20% chance)
        $dates = $factory->callGetDateRange(true);
        $this->assertSame(
            $dates['start_date'] === null,
            $dates['end_date'] === null,
        );
    }

    public function test_get_record_static_method_works_directly(): void
    {
        // Test the static getRecord method directly on the factory (where the trait method is available)
        $record = AuditedRecordFactory::getRecord(AuditedRecord::class);

        $this->assertInstanceOf(AuditedRecord::class, $record);
        $this->assertDatabaseHas('audited_records', ['id' => $record->id]);
    }
}
