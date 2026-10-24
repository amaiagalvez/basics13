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
 * @phpstan-type MenuItem array{key: string, parent_key: string|null, label: string, icon: string|null, route: string|null, active_pattern: string|null, active_except: string|null, permissions: list<string>}
 * @phpstan-type MenuEntry array{item: MenuItem, children: list<MenuItem>}
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
     *
     * @return list<MenuEntry>
     */
    public static function for(Authenticatable $user): array
    {
        $items = self::items();
        $entries = [];

        foreach ($items as $item) {
            if ($item['parent_key'] !== null) {
                continue;
            }

            if ($item['route'] !== null) {
                if (self::shows($item, $user)) {
                    $entries[] = ['item' => $item, 'children' => []];
                }

                continue;
            }

            $children = array_values(array_filter(
                $items,
                static fn (array $child): bool => $child['parent_key'] === $item['key'] && $child['route'] !== null && self::shows($child, $user),
            ));

            if ($children !== []) {
                $entries[] = ['item' => $item, 'children' => $children];
            }
        }

        return $entries;
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
