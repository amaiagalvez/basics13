<?php

namespace Basics13\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * The canonical soft-deleting record behind the transaction: the trait is tested through it, the
 * way the domain packages apply it on their own resources.
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name'])]
class SoftDeletingRecord extends Model
{
    /** @use HasFactory<SoftDeletingRecordFactory> */
    use HasFactory, SoftDeletes, TransactionalDelete;

    protected static function newFactory(): SoftDeletingRecordFactory
    {
        return SoftDeletingRecordFactory::new();
    }
}
