<?php

namespace Basics13\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A record that goes when it is deleted: with no soft deletes there is no trashed state to keep
 * the write atomic with the hooks that run before it, so the trait steps aside.
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name'])]
class HardDeletingRecord extends Model
{
    /** @use HasFactory<HardDeletingRecordFactory> */
    use HasFactory, TransactionalDelete;

    protected static function newFactory(): HardDeletingRecordFactory
    {
        return HardDeletingRecordFactory::new();
    }
}
