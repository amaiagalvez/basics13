<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * The managed email screens: `email.view` opens them and `email.edit` covers every write, the send
 * test included. `email.manage` goes to the `admin` role, as every other basics13 module does. Both
 * links hang from the administration group. Every write only adds: running it again duplicates
 * nothing and takes no permission away from a role.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = ['email.view', 'email.edit', 'email.manage'];

    public function up(): void
    {
        $now = now();

        DB::table('BAS_permissions')->insertOrIgnore(array_map(
            static fn (string $slug): array => [
                'slug' => $slug,
                'module' => 'email',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::PERMISSIONS,
        ));

        $adminId = DB::table('BAS_roles')->whereNull('deleted_at')->where('slug', 'admin')->value('id');

        if ($adminId !== null) {
            DB::table('BAS_permission_role')->insertOrIgnore([
                'permission_id' => DB::table('BAS_permissions')->where('slug', 'email.manage')->value('id'),
                'role_id' => $adminId,
            ]);
        }

        seedMenus('basics13', [
            ['key' => 'email-configurations', 'parent' => 'administration', 'label' => 'SMTP configurations', 'sort' => 90, 'icon' => 'envelope', 'route' => 'email-configurations.index', 'active' => 'email-configurations.*', 'permissions' => ['email.view']],
            ['key' => 'email-templates', 'parent' => 'administration', 'label' => 'Email templates', 'sort' => 100, 'icon' => 'document-text', 'route' => 'email-templates.index', 'active' => 'email-templates.*', 'permissions' => ['email.view']],
        ]);
    }

    public function down(): void
    {
        DB::table('BAS_menus')->whereIn('key', ['email-configurations', 'email-templates'])->delete();
    }
};
