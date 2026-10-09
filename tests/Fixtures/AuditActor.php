<?php

namespace Basics13\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name'])]
class AuditActor extends Authenticatable
{
    /** @use HasFactory<AuditActorFactory> */
    use HasFactory;

    protected $table = 'users';

    /**
     * The trait is tested in Basics13\Tests\Unit\Concerns, so it resolves no factory by
     * convention: package fixtures name their own.
     */
    protected static function newFactory(): AuditActorFactory
    {
        return AuditActorFactory::new();
    }
}
