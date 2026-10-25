<?php

namespace Basics13\Support;

use Basics13\Models\Menu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The sidebar of an account, built from the items the packages seed.
 *
 * Every item, with the slugs of its permissions, is cached under one key that only the seeding
 * clears, and read once per request. What an account sees is filtered in memory with the
 * permissions HasRoles already loaded, so painting the menu adds no query and is never stale.
 *
 * The tree has three levels at most: a group holds links and subgroups, a subgroup holds links.
 *
 * @phpstan-type MenuItem array{key: string, parent_key: string|null, label: string, icon: string|null, route: string|null, active_pattern: string|null, active_except: string|null, permissions: list<string>}
 * @phpstan-type MenuLink array{item: MenuItem, children: array{}}
 * @phpstan-type MenuSubgroup array{item: MenuItem, children: non-empty-list<MenuLink>}
 * @phpstan-type MenuGroup array{item: MenuItem, children: non-empty-list<MenuLink|MenuSubgroup>}
 * @phpstan-type MenuEntry MenuLink|MenuGroup
 */
class NavigationMenu
{
    public const CACHE_KEY = 'basics13.menu.items';

    /**
     * Every seeded item, ordered.
     *
     * @return list<MenuItem>
     */
    public static function items(): array
    {
        /** @var list<MenuItem> */
        return Cache::memo()->rememberForever(self::CACHE_KEY, static fn (): array => Menu::query()
            ->with('permissions:id,slug')
            ->orderBy('sort')
            ->orderBy('key')
            ->get()
            ->map(static fn (Menu $menu): array => [
                'key' => $menu->key,
                'parent_key' => $menu->parent_key,
                'label' => $menu->label,
                'icon' => $menu->icon,
                'route' => $menu->route,
                'active_pattern' => $menu->active_pattern,
                'active_except' => $menu->active_except,
                'permissions' => $menu->permissions->pluck('slug')->values()->all(),
            ])
            ->values()
            ->all());
    }

    public static function flush(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    /**
     * The top-level entries the account sees: links, and groups with at least one visible child.
     * A subgroup without visible links is left out like an empty group.
     *
     * @return list<MenuEntry>
     */
    public static function for(Authenticatable $user): array
    {
        /** @var array<string, list<MenuItem>> $childrenOf */
        $childrenOf = [];

        foreach (self::items() as $item) {
            $childrenOf[$item['parent_key'] ?? ''][] = $item;
        }

        $entries = [];

        foreach ($childrenOf[''] ?? [] as $item) {
            if ($item['route'] !== null) {
                if (self::shows($item, $user)) {
                    $entries[] = ['item' => $item, 'children' => []];
                }

                continue;
            }

            $children = [];

            foreach ($childrenOf[$item['key']] ?? [] as $child) {
                if ($child['route'] !== null) {
                    if (self::shows($child, $user)) {
                        $children[] = ['item' => $child, 'children' => []];
                    }

                    continue;
                }

                $links = self::links($childrenOf[$child['key']] ?? [], $user);

                if ($links !== []) {
                    $children[] = ['item' => $child, 'children' => $links];
                }
            }

            if ($children !== []) {
                $entries[] = ['item' => $item, 'children' => $children];
            }
        }

        return $entries;
    }

    /**
     * The links among these items the account sees.
     *
     * @param  list<MenuItem>  $items
     * @return list<MenuLink>
     */
    private static function links(array $items, Authenticatable $user): array
    {
        $links = [];

        foreach ($items as $item) {
            if ($item['route'] !== null && self::shows($item, $user)) {
                $links[] = ['item' => $item, 'children' => []];
            }
        }

        return $links;
    }

    /**
     * @param  MenuItem  $item
     */
    private static function shows(array $item, Authenticatable $user): bool
    {
        if ($item['route'] === null || ! Route::has($item['route'])) {
            return false;
        }

        if ($item['permissions'] === []) {
            return true;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        foreach ($item['permissions'] as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
