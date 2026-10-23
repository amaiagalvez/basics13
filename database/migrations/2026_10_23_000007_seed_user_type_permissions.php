<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * The permissions of the user types and of the delegated management of accounts: only the ones the
 * policies check. `user_types.manage` goes to the `admin` role so the accounts that already hold it
 * keep managing every catalogue; `users.view_own` and `users.manage_own` are granted by hand. Every
 * write only adds: running it again duplicates nothing and takes no permission away from a role.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'user_types.view',
        'user_types.create',
        'user_types.edit',
        'user_types.delete',
        'user_types.restore',
        'user_types.force_delete',
        'user_types.archive',
        'user_types.manage',
        'users.view_own',
        'users.manage_own',
    ];

    /** @var list<string> */
    private const GRANT_TO_ADMIN = ['user_types.manage'];

    public function up(): void
    {
        $now = now();

        DB::table('BAS_permissions')->insertOrIgnore(array_map(
            static fn (string $slug): array => [
                'slug' => $slug,
                'module' => explode('.', $slug, 2)[0],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::PERMISSIONS,
        ));

        $adminId = DB::table('BAS_roles')->whereNull('deleted_at')->where('slug', 'admin')->value('id');

        if ($adminId === null) {
            return;
        }

        DB::table('BAS_permission_role')->insertOrIgnore(DB::table('BAS_permissions')
            ->whereIn('slug', self::GRANT_TO_ADMIN)
            ->pluck('id')
            ->map(static fn (mixed $permissionId): array => ['permission_id' => $permissionId, 'role_id' => $adminId])
            ->all());
    }

    public function down(): void
    {
        // The table these rows live in is dropped by the migration that created it.
    }
};
