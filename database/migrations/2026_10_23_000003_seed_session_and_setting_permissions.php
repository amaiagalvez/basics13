<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * The permissions of the two modules this package adds with the session log and the application
 * configuration: only the ones their policies check. `sessions.manage` and `settings.manage` go
 * to the `admin` role so the accounts that already hold it keep their access. Every write only
 * adds: running it again duplicates nothing and takes no permission away from a role.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'sessions.view',
        'sessions.manage',
        'settings.view',
        'settings.edit',
        'settings.manage',
    ];

    /** @var list<string> */
    private const GRANT_TO_ADMIN = ['sessions.manage', 'settings.manage'];

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
