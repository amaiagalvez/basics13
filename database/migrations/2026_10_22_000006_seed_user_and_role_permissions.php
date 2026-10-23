<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * The permissions of the two modules basics13 checks, the three starting roles, and the `admin`
 * role for every account that already exists, so nobody loses access when permissions start being
 * checked. A migration rather than a seeder: it has to run on every deploy and under
 * RefreshDatabase. Every write is idempotent and only adds, so running it again neither duplicates
 * a row nor takes a permission away from a role.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const MODULES = ['users', 'roles'];

    /** @var list<string> */
    private const ACTIONS = ['view', 'create', 'edit', 'delete', 'archive', 'restore', 'force_delete', 'impersonate', 'manage'];

    /**
     * @var array<string, array{name: string, is_system: bool, permissions: list<string>}>
     */
    private const ROLES = [
        'admin' => [
            'name' => 'Admin',
            'is_system' => true,
            'permissions' => ['users.manage', 'roles.manage'],
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

    public function up(): void
    {
        $now = now();
        $permissions = [];

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = ['slug' => $module.'.'.$action, 'module' => $module, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::table('permissions')->insertOrIgnore($permissions);

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        foreach (self::ROLES as $slug => $role) {
            $roleId = $this->roleId($slug, $role['name'], $role['is_system']);

            DB::table('permission_role')->insertOrIgnore(array_map(
                static fn (string $permission): array => ['permission_id' => $permissionIds[$permission], 'role_id' => $roleId],
                $role['permissions'],
            ));
        }

        $adminId = $this->roleId('admin', self::ROLES['admin']['name'], true);

        // The super-admin holds every permission without roles and never receives one.
        DB::table('users')
            ->whereNull('deleted_at')
            ->where('is_super_admin', false)
            ->chunkById(500, static function ($users) use ($adminId, $now): void {
                DB::table('role_user')->insertOrIgnore($users->map(static fn (object $user): array => [
                    'role_id' => $adminId,
                    'user_id' => $user->id,
                    'created_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        // The tables these rows live in are dropped by the migrations that created them.
    }

    /**
     * The role with this slug outside the trash, created when there is none.
     */
    private function roleId(string $slug, string $name, bool $isSystem): int
    {
        $id = DB::table('roles')->whereNull('deleted_at')->where('slug', $slug)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_system' => $isSystem,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
