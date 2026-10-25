<?php

use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Basics13\Support\NavigationMenu;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Check if the database driver is SQLite or PostgreSQL.
 */
function isSqliteOrPgsql(): bool
{
    $driver = DB::connection()->getDriverName();

    return in_array($driver, ['sqlite', 'pgsql'], true);
}

/**
 * Add the columns shared by the application's soft-deleting tables.
 */
function addCommonColumns(Blueprint $table): void
{
    $table->longText('notes')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['deleted_at', 'id']);
}

/**
 * Add the three audit columns that record who created, last changed and trashed a row.
 */
function addAuditColumns(Blueprint $table): void
{
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->foreignId('updated_by')->nullable()->constrained('users');
    $table->foreignId('deleted_by')->nullable()->constrained('users');
}

/**
 * Make a table's name unique among the rows that are not soft-deleted.
 *
 * @param  list<string>  $scope  Extra columns the name is unique within, besides [name] itself
 */
function addUniqueActiveNameIndex(string $table, array $scope = []): void
{
    addUniqueActiveColumnIndex($table, 'name', $scope);
}

/**
 * Make one column unique among the rows that are not soft-deleted.
 *
 * The same guarantee as addUniqueActiveNameIndex(), for a table that keeps a value to be unique in
 * a column that is not called `name`, such as one storing the value once per locale.
 *
 * @param  string  $column  The column whose value has to stay unique
 * @param  list<string>  $scope  Extra columns the value is unique within, besides $column itself
 */
function addUniqueActiveColumnIndex(string $table, string $column, array $scope = []): void
{
    $index = implode('_', array_merge([$table], $scope, ['active_'.$column, 'unique']));

    if (isSqliteOrPgsql()) {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s IS NULL',
            $grammar->wrap($index),
            $grammar->wrapTable($table),
            implode(', ', array_map(
                fn (string $name): string => $grammar->wrap($name),
                [...$scope, $column],
            )),
            $grammar->wrap('deleted_at'),
        ));

        return;
    }

    $generated = 'active_'.$column;

    Schema::table($table, static function (Blueprint $blueprint) use ($scope, $generated, $column, $index): void {
        $blueprint->string($generated)->nullable()->storedAs('IF(deleted_at IS NULL, '.$column.', NULL)');
        $blueprint->unique([...$scope, $generated], $index);
    });
}

/**
 * Seed the permissions of the modules a package's policies check, and grant some of them to the
 * system role `admin` so the accounts that already hold it keep their access.
 *
 * Every write only adds: running it again duplicates nothing and takes no permission away from a
 * role. Meant for a package's access seeder, after the basics13 one that creates `admin`.
 *
 * @param  list<string>  $modules  Each module gets one permission per action of Permission::ACTIONS
 * @param  list<string>  $grantToAdmin  Slugs added to the `admin` role
 * @param  list<string>  $slugs  Loose `{module}.{action}` permissions, for modules that check only some actions
 */
function seedPermissions(array $modules, array $grantToAdmin = [], array $slugs = []): void
{
    $now = now();
    $permissions = [];

    foreach ($modules as $module) {
        foreach (Permission::ACTIONS as $action) {
            $permissions[] = ['slug' => $module.'.'.$action, 'module' => $module, 'created_at' => $now, 'updated_at' => $now];
        }
    }

    foreach ($slugs as $slug) {
        $permissions[] = ['slug' => $slug, 'module' => explode('.', $slug, 2)[0], 'created_at' => $now, 'updated_at' => $now];
    }

    DB::table('BAS_permissions')->insertOrIgnore($permissions);

    $adminId = DB::table('BAS_roles')->whereNull('deleted_at')->where('slug', 'admin')->value('id');

    if ($adminId === null || $grantToAdmin === []) {
        return;
    }

    DB::table('BAS_permission_role')->insertOrIgnore(DB::table('BAS_permissions')
        ->whereIn('slug', $grantToAdmin)
        ->pluck('id')
        ->map(static fn (mixed $permissionId): array => ['permission_id' => $permissionId, 'role_id' => $adminId])
        ->all());
}

/**
 * Seed a package's menu items and leave each one with exactly the permissions listed, so a later
 * version can take a permission away. Every slug MUST exist: an item left without permissions
 * would show to every account, so an unknown slug throws before anything is written.
 *
 * Meant for a package's access seeder, after it seeds its permissions.
 *
 * @param  list<array{key: string, label: string, sort: int, parent?: string, icon?: string, route?: string, active?: string, except?: string, permissions?: list<string>}>  $items
 */
function seedMenus(string $package, array $items): void
{
    $slugs = array_values(array_unique(array_merge(...array_map(
        static fn (array $item): array => $item['permissions'] ?? [],
        $items,
    ))));
    $permissionIds = DB::table('BAS_permissions')->whereIn('slug', $slugs)->pluck('id', 'slug');
    $unknown = array_diff($slugs, $permissionIds->keys()->all());

    if ($unknown !== []) {
        throw new RuntimeException("Menus of {$package} use unknown permissions: ".implode(', ', $unknown));
    }

    DB::transaction(static function () use ($package, $items, $permissionIds): void {
        $now = now();

        DB::table('BAS_menus')->upsert(array_map(static fn (array $item): array => [
            'key' => $item['key'],
            'parent_key' => $item['parent'] ?? null,
            'package' => $package,
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'route' => $item['route'] ?? null,
            'active_pattern' => $item['active'] ?? null,
            'active_except' => $item['except'] ?? null,
            'sort' => $item['sort'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $items), ['key'], ['parent_key', 'package', 'label', 'icon', 'route', 'active_pattern', 'active_except', 'sort', 'updated_at']);

        $menuIds = DB::table('BAS_menus')->whereIn('key', array_column($items, 'key'))->pluck('id', 'key');

        DB::table('BAS_menu_permission')->whereIn('menu_id', $menuIds->values())->delete();
        DB::table('BAS_menu_permission')->insert(array_merge(...array_map(
            static fn (array $item): array => array_map(
                static fn (string $slug): array => ['menu_id' => $menuIds[$item['key']], 'permission_id' => $permissionIds[$slug]],
                $item['permissions'] ?? [],
            ),
            $items,
        )));
    });

    NavigationMenu::flush();
}

/**
 * Remove the menu items a package seeded; their permission links go with them.
 */
function removeMenus(string $package): void
{
    DB::table('BAS_menus')->where('package', $package)->delete();

    NavigationMenu::flush();
}
