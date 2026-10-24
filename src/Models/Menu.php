<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One entry of the sidebar: a group (no route) or a link. Items are seeded by the package that
 * owns their routes and only read by the application. A link shows to whoever holds any of its
 * permissions, or to every account when it has none.
 *
 * @property int $id
 * @property string $key
 * @property string|null $parent_key
 * @property string $package
 * @property string $label
 * @property string|null $icon
 * @property string|null $route
 * @property string|null $active_pattern
 * @property string|null $active_except
 * @property int $sort
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'parent_key', 'package', 'label', 'icon', 'route', 'active_pattern', 'active_except', 'sort'])]
class Menu extends Model
{
    protected $table = 'BAS_menus';

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'BAS_menu_permission');
    }
}
