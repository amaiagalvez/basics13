<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Basics13\Support\Database\UniqueConstraintViolation;

/**
 * The guarantees of the role tables that the database itself enforces, so no code path can skip
 * them. Shared by the SQLite run and the MariaDB one, whose unique indexes are built differently.
 */
trait AssertsRoleSchema
{
    public function test_name_and_slug_are_unique_among_roles_outside_the_trash_archived_ones_included(): void
    {
        foreach (['name', 'slug'] as $column) {
            $this->insertRole(['name' => 'Taken '.$column, 'slug' => 'taken-'.$column, 'active' => false]);

            try {
                $this->insertRole([
                    'name' => $column === 'name' ? 'Taken name' : 'Other '.$column,
                    'slug' => $column === 'slug' ? 'taken-slug' : 'other-'.$column,
                ]);
                $this->fail("A duplicated {$column} must violate the unique index.");
            } catch (QueryException $exception) {
                $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
            }
        }
    }

    public function test_name_and_slug_are_reusable_once_the_role_is_in_the_trash(): void
    {
        $this->insertRole(['name' => 'Editors', 'slug' => 'editors', 'deleted_at' => now()]);
        $this->insertRole(['name' => 'Editors', 'slug' => 'editors']);

        $this->assertSame(2, DB::table('roles')->where('slug', 'editors')->count());
    }

    public function test_a_role_held_by_an_account_cannot_be_deleted(): void
    {
        $roleId = $this->insertRole(['name' => 'Editors', 'slug' => 'editors']);
        $userId = User::factory()->create()->id;
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $userId, 'created_at' => now()]);

        try {
            DB::table('roles')->where('id', $roleId)->delete();
            $this->fail('The foreign key of role_user must restrict the delete.');
        } catch (QueryException) {
            $this->assertDatabaseHas('roles', ['id' => $roleId]);
        }

        // Deleting the account drops its assignments, and then the role can go.
        DB::table('users')->where('id', $userId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_users_have_a_super_admin_flag_that_defaults_to_false(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Plain account',
            'email' => 'plain@example.test',
            'password' => 'secret',
        ]);

        $this->assertFalse((bool) DB::table('users')->where('id', $userId)->value('is_super_admin'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertRole(array $attributes): int
    {
        return DB::table('roles')->insertGetId([...$attributes, 'created_at' => now(), 'updated_at' => now()]);
    }
}
