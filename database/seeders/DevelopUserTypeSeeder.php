<?php

namespace Basics13\Database\Seeders;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Models\UserType;
use Illuminate\Database\Seeder;

/**
 * Example user types for a development database, with an account that manages the users of one of
 * them through `users.manage_own`, so the delegated management can be tried by signing in as it.
 */
class DevelopUserTypeSeeder extends Seeder
{
    public function run(): void
    {
        $managed = UserType::factory()->create([
            'name_eu' => 'Irakasleak',
            'name_es' => 'Profesorado',
            'name_fr' => 'Enseignants',
            'name_en' => 'Teachers',
        ]);

        UserType::factory()->create([
            'name_eu' => 'Ikasleak',
            'name_es' => 'Alumnado',
            'name_fr' => 'Élèves',
            'name_en' => 'Students',
        ]);

        UserType::factory()->archived()->create([
            'name_eu' => 'Erabiltzaile mota zaharkitua',
            'name_es' => 'Tipo de usuario archivado',
            'name_fr' => 'Type d’utilisateur archivé',
            'name_en' => 'Archived user type',
        ]);

        $role = Role::factory()->withPermissions(['users.manage_own'])->create(['name' => 'Type admin', 'slug' => 'type-admin']);

        $admin = User::factory()->create(['name' => 'Type Admin', 'email' => 'type.admin@basics.test']);
        $admin->assignRole($role);
        $admin->userTypes()->attach($managed->id, ['is_admin' => true]);

        User::factory()->count(3)->create()->each(
            static fn (User $user) => $user->userTypes()->attach($managed->id),
        );
    }
}
