<?php

namespace Basics13\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * The canonical soft-deleting audited record: the trait is tested through it, the way an
 * application uses it on its own resources.
 *
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'notes'])]
class AuditedRecord extends Model
{
    /** @use HasFactory<AuditedRecordFactory> */
    use HasFactory, SoftDeletes, TracksAuditColumns;

    /**
     * @var array{active: bool}
     */
    protected $attributes = [
        'active' => true,
    ];

    protected static function newFactory(): AuditedRecordFactory
    {
        return AuditedRecordFactory::new();
    }
}
