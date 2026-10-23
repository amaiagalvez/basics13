<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Basics13\Policies\UserTypePolicy;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\SoftDeletes;
use Basics13\Database\Factories\UserTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name_eu
 * @property string|null $name_es
 * @property string|null $name_fr
 * @property string|null $name_en
 * @property string|null $notes
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 */
#[Fillable(['name_eu', 'name_es', 'name_fr', 'name_en', 'notes'])]
#[UsePolicy(UserTypePolicy::class)]
#[UseFactory(UserTypeFactory::class)]
class UserType extends Model
{
    /** @use HasFactory<UserTypeFactory> */
    use HasFactory, ResolvesUserModel, SoftDeletes, TracksAuditColumns, TransactionalDelete;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'BAS_user_types';

    /**
     * @var array{active: bool}
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array{active: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Language columns for the type name.
     *
     * @var list<string>
     */
    public const NAME_FIELDS = ['name_eu', 'name_es', 'name_fr', 'name_en'];

    /**
     * The accounts holding this type, over the model the host configures. Trashed accounts are
     * excluded, as in any relation the application reads; the delete guard below counts the pivot
     * rows instead, because those still hold the type.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany($this->userModelClass(), 'BAS_user_user_type', 'user_type_id', 'user_id')
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    /**
     * A type that an account holds cannot be dropped, not even when that account is in the trash.
     * The permanent delete is refused by the foreign key of the pivot table; this hook covers the
     * soft delete, and takes the row lock in a transaction of its own that TransactionalDelete keeps
     * held until the delete is written, so an account given the type in between makes the delete
     * fail instead of leaving a type in the trash that an account still points at.
     */
    protected static function booted(): void
    {
        static::deleting(static function (self $userType): bool {
            $locked = DB::transaction(static fn (): self => self::withTrashed()
                ->whereKey($userType->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! DB::table('BAS_user_user_type')
                ->where('user_type_id', $locked->getKey())
                ->exists();
        });
    }
}
