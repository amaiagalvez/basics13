<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One `{module}.{action}` ability a policy checks. Permissions are seeded by the package that owns
 * the module and only read by the application: there is no trash, archive or form for them. The
 * visible label is the translation of the slug.
 *
 * @property int $id
 * @property string $slug
 * @property string $module
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'module'])]
#[UseFactory(PermissionFactory::class)]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * The actions a permission slug may end with. `manage` grants every other action of its module.
     *
     * @var list<string>
     */
    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'archive', 'restore', 'force_delete', 'manage'];

    /**
     * The translated label of the permission. Each package translates the slugs it seeds.
     */
    public function label(): string
    {
        return __($this->slug);
    }

    /**
     * The translated name of a module, as the form, the permission list and the summary show it:
     * `entity_types` reads "Entity types".
     */
    public static function moduleLabel(string $module): string
    {
        return __(ucfirst(str_replace('_', ' ', $module)));
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
