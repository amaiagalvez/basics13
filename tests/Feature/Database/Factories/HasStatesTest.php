<?php

namespace Basics13\Tests\Feature\Database\Factories;

use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Tests\Fixtures\AuditedRecordFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class HasStatesTest extends TestCase
{
    use LazilyRefreshDatabase;

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

        $record = AuditedRecordFactory::getRecordOrNew(AuditedRecord::class);

        $this->assertSame($existing->id, $record->id);
    }

    public function test_get_record_creates_new_when_none_exists(): void
    {
        $record = AuditedRecordFactory::getRecordOrNew(AuditedRecord::class);

        $this->assertDatabaseHas('audited_records', ['id' => $record->id]);
    }

    public function test_get_record_static_method_works_directly(): void
    {
        // Test the static getRecordOrNew method directly on the factory (where the trait method is available)
        $record = AuditedRecordFactory::getRecordOrNew(AuditedRecord::class);

        $this->assertInstanceOf(AuditedRecord::class, $record);
        $this->assertDatabaseHas('audited_records', ['id' => $record->id]);
    }
}
