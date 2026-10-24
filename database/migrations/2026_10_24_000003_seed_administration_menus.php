<?php

use Illuminate\Database\Migrations\Migration;

/**
 * The administration group of the sidebar. Each link asks for the permissions its page's policy
 * checks; the active patterns leave out the pages another link of the group owns.
 */
return new class extends Migration
{
    public function up(): void
    {
        seedMenus('basics13', [
            ['key' => 'administration', 'label' => 'Administration', 'sort' => 900],
            ['key' => 'admin-dashboard', 'parent' => 'administration', 'label' => 'Administration dashboard', 'sort' => 10, 'icon' => 'chart-bar', 'route' => 'sessions.dashboard', 'active' => 'sessions.dashboard', 'permissions' => ['sessions.view']],
            ['key' => 'users', 'parent' => 'administration', 'label' => 'Users', 'sort' => 20, 'icon' => 'user-circle', 'route' => 'users.index', 'active' => 'users.*', 'except' => 'users.permissions-summary.*', 'permissions' => ['users.view', 'users.view_own', 'users.manage_own']],
            ['key' => 'user-types', 'parent' => 'administration', 'label' => 'User types', 'sort' => 30, 'icon' => 'tag', 'route' => 'user-types.index', 'active' => 'user-types.*', 'permissions' => ['user_types.view']],
            ['key' => 'roles', 'parent' => 'administration', 'label' => 'Roles', 'sort' => 40, 'icon' => 'shield-check', 'route' => 'roles.index', 'active' => 'roles.*', 'permissions' => ['roles.view']],
            ['key' => 'permissions', 'parent' => 'administration', 'label' => 'Permissions', 'sort' => 50, 'icon' => 'key', 'route' => 'permissions.index', 'active' => 'permissions.*', 'permissions' => ['roles.view']],
            ['key' => 'permissions-summary', 'parent' => 'administration', 'label' => 'Permissions summary', 'sort' => 60, 'icon' => 'table-cells', 'route' => 'users.permissions-summary.index', 'active' => 'users.permissions-summary.*', 'permissions' => ['roles.view']],
            ['key' => 'sessions', 'parent' => 'administration', 'label' => 'Sessions', 'sort' => 70, 'icon' => 'finger-print', 'route' => 'sessions.index', 'active' => 'sessions.*', 'except' => 'sessions.dashboard', 'permissions' => ['sessions.view']],
            ['key' => 'configuration', 'parent' => 'administration', 'label' => 'Application configuration', 'sort' => 80, 'icon' => 'adjustments-horizontal', 'route' => 'configuration.index', 'active' => 'configuration.*', 'permissions' => ['settings.view']],
        ]);
    }

    public function down(): void
    {
        removeMenus('basics13');
    }
};
