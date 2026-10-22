<?php

namespace Basics13\Tests\Unit\Concerns;

use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Basics13\Tests\Fixtures\PlainAuditedRecord;

class TracksAuditColumnsTest extends TestCase
{
    /**
     * The audited records the trait writes its columns for: AuditedRecord keeps a deleted_at, while
     * PlainAuditedRecord has no soft deletes, so it is the one whose deleted_by is never written.
     *
     * @return array<string, array{class-string}>
     */
    public static function auditedRecords(): array
    {
        return [
            'audited record' => [AuditedRecord::class],
            'plain audited record' => [PlainAuditedRecord::class],
        ];
    }

    /**
     * The columns are written by the trait alone, so a payload cannot claim authorship.
     *
     * @param  class-string<AuditedRecord|PlainAuditedRecord>  $record
     */
    #[DataProvider('auditedRecords')]
    public function test_the_audit_columns_are_not_mass_assignable(string $record): void
    {
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            $this->assertFalse((new $record)->isFillable($column), "{$record} must not accept {$column}");
        }
    }
}
