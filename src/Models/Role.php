<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Basics13\Policies\RolePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Basics13\Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of permissions given to accounts. `is_system` and `active` are never mass assigned:
 * a system role comes only from a data migration, and the flag changes only through the archive.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 * @property string|null $notes
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'slug', 'description', 'notes'])]
#[UsePolicy(RolePolicy::class)]
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, ResolvesUserModel, SoftDeletes, TracksAuditColumns, TransactionalDelete;

    protected $table = 'BAS_roles';

    /**
     * @var array{active: bool, is_system: bool}
     */
    protected $attributes = [
        'active' => true,
        'is_system' => false,
    ];

    /**
     * @return array{active: 'boolean', is_system: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'BAS_permission_role');
    }

    /**
     * The accounts holding the role, over the model the host configures. Trashed accounts are
     * excluded, as in any relation the application reads; the delete guard below counts the pivot
     * rows instead, because those still hold the role.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany($this->userModelClass(), 'BAS_role_user', 'role_id', 'user_id');
    }

    /**
     * A role an account holds cannot be dropped, not even when that account is in the trash. The
     * permanent delete is refused by the foreign key of the pivot table; this hook covers the soft
     * delete, and takes the row lock in a transaction of its own that TransactionalDelete keeps held
     * until the delete is written, so an account given the role in between makes the delete fail.
     */
    protected static function booted(): void
    {
        static::deleting(static function (self $role): bool {
            $locked = DB::transaction(static fn (): self => self::withTrashed()
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! DB::table('BAS_role_user')
                ->where('role_id', $locked->getKey())
                ->exists();
        });
    }
}
