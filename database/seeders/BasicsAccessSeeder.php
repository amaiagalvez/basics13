<?php

namespace Basics13\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The starting roles, the permissions of the modules basics13 checks and the administration group
 * of the sidebar. Runs before the access seeders of the domain packages, which grant to `admin`.
 * Every write is idempotent and only adds permissions: running it again duplicates nothing and
 * takes no permission away from a role edited in the interface.
 */
class BasicsAccessSeeder extends Seeder
{
    /** @var list<string> */
    private const MODULES = ['users', 'roles'];

    /**
     * Modules whose policies check only some actions, plus the actions outside Permission::ACTIONS.
     *
     * @var list<string>
     */
    private const SLUGS = [
        'users.impersonate',
        'roles.impersonate',
        'users.view_own',
        'users.manage_own',
        'sessions.view',
        'sessions.manage',
        'settings.view',
        'settings.edit',
        'settings.manage',
        'user_types.view',
        'user_types.create',
        'user_types.edit',
        'user_types.delete',
        'user_types.restore',
        'user_types.force_delete',
        'user_types.archive',
        'user_types.manage',
        'email.view',
        'email.edit',
        'email.manage',
    ];

    /**
     * @var array<string, array{name: string, is_system: bool, permissions: list<string>}>
     */
    private const ROLES = [
        'admin' => [
            'name' => 'Admin',
            'is_system' => true,
            'permissions' => ['users.manage', 'roles.manage', 'sessions.manage', 'settings.manage', 'user_types.manage', 'email.manage'],
        ],
        'manager' => [
            'name' => 'Manager',
            'is_system' => false,
            'permissions' => ['users.view', 'users.create', 'users.edit', 'roles.view'],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'is_system' => false,
            'permissions' => ['users.view', 'roles.view'],
        ],
    ];

    /**
     * Each link asks for the permissions its page's policy checks; the active patterns leave out
     * the pages another link of the group owns.
     *
     * @var list<array{key: string, label: string, sort: int, parent?: string, icon?: string, route?: string, active?: string, except?: string, permissions?: list<string>}>
     */
    private const MENUS = [
        ['key' => 'basics13', 'label' => 'Management', 'sort' => 100],
        ['key' => 'basics-auxiliary', 'parent' => 'basics13', 'label' => 'Auxiliary tables', 'sort' => 900],
        ['key' => 'admin-dashboard', 'parent' => 'basics13', 'label' => 'Administration dashboard', 'sort' => 10, 'icon' => 'chart-bar', 'route' => 'sessions.dashboard', 'active' => 'sessions.dashboard', 'permissions' => ['sessions.view']],
        ['key' => 'users', 'parent' => 'basics13', 'label' => 'Users', 'sort' => 20, 'icon' => 'user-circle', 'route' => 'users.index', 'active' => 'users.*', 'except' => 'users.permissions-summary.*', 'permissions' => ['users.view', 'users.view_own', 'users.manage_own']],
        ['key' => 'user-types', 'parent' => 'basics-auxiliary', 'label' => 'User types', 'sort' => 30, 'icon' => 'tag', 'route' => 'user-types.index', 'active' => 'user-types.*', 'permissions' => ['user_types.view']],
        ['key' => 'roles', 'parent' => 'basics13', 'label' => 'Roles', 'sort' => 40, 'icon' => 'shield-check', 'route' => 'roles.index', 'active' => 'roles.*', 'permissions' => ['roles.view']],
        ['key' => 'permissions', 'parent' => 'basics13', 'label' => 'Permissions', 'sort' => 50, 'icon' => 'key', 'route' => 'permissions.index', 'active' => 'permissions.*', 'permissions' => ['roles.view']],
        ['key' => 'permissions-summary', 'parent' => 'basics13', 'label' => 'Permissions summary', 'sort' => 60, 'icon' => 'table-cells', 'route' => 'users.permissions-summary.index', 'active' => 'users.permissions-summary.*', 'permissions' => ['roles.view']],
        ['key' => 'sessions', 'parent' => 'basics13', 'label' => 'Sessions', 'sort' => 70, 'icon' => 'finger-print', 'route' => 'sessions.index', 'active' => 'sessions.*', 'except' => 'sessions.dashboard', 'permissions' => ['sessions.view']],
        ['key' => 'configuration', 'parent' => 'basics13', 'label' => 'Application configuration', 'sort' => 80, 'icon' => 'adjustments-horizontal', 'route' => 'configuration.index', 'active' => 'configuration.*', 'permissions' => ['settings.view']],
        ['key' => 'email-configurations', 'parent' => 'basics13', 'label' => 'SMTP configurations', 'sort' => 90, 'icon' => 'envelope', 'route' => 'email-configurations.index', 'active' => 'email-configurations.*', 'permissions' => ['email.view']],
        ['key' => 'email-templates', 'parent' => 'basics13', 'label' => 'Email templates', 'sort' => 100, 'icon' => 'document-text', 'route' => 'email-templates.index', 'active' => 'email-templates.*', 'permissions' => ['email.view']],
    ];

    public function run(): void
    {
        $roleIds = [];

        foreach (self::ROLES as $slug => $role) {
            $roleIds[$slug] = $this->roleId($slug, $role['name'], $role['is_system']);
        }

        seedPermissions(self::MODULES, self::ROLES['admin']['permissions'], self::SLUGS);

        $permissionIds = DB::table('BAS_permissions')->pluck('id', 'slug');

        foreach (self::ROLES as $slug => $role) {
            DB::table('BAS_permission_role')->insertOrIgnore(array_map(
                static fn (string $permission): array => ['permission_id' => $permissionIds[$permission], 'role_id' => $roleIds[$slug]],
                $role['permissions'],
            ));
        }

        seedMenus('basics13', self::MENUS);
    }

    /**
     * The role with this slug outside the trash, created when there is none.
     */
    private function roleId(string $slug, string $name, bool $isSystem): int
    {
        $id = DB::table('BAS_roles')->whereNull('deleted_at')->where('slug', $slug)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('BAS_roles')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_system' => $isSystem,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
