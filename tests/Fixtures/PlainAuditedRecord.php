<?php

namespace Basics13\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * An audited record with no soft deletes: the trait skips every deleted_by branch for it, because
 * such a model has no trashed state to attribute a deletion to.
 *
 * @property int $id
 * @property string $name
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name'])]
class PlainAuditedRecord extends Model
{
    /** @use HasFactory<PlainAuditedRecordFactory> */
    use HasFactory, TracksAuditColumns;

    protected static function newFactory(): PlainAuditedRecordFactory
    {
        return PlainAuditedRecordFactory::new();
    }
}
