<?php

namespace Basics13\Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Basics13\Tests\Feature\FeatureTestCase;

final class AuthMigrationCompatibilityTest extends FeatureTestCase
{
    public function test_existing_auth_data_is_preserved_when_the_package_is_installed(): void
    {
        $this->createExistingAuthSchema();

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => null,
            'notes' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'two_factor_secret' => 'existing-secret',
            'two_factor_recovery_codes' => 'existing-codes',
            'two_factor_confirmed_at' => now(),
        ]);

        DB::table('passkeys')->insert([
            'id' => 1,
            'user_id' => 1,
            'name' => 'Existing passkey',
            'credential_id' => 'existing-credential',
            'credential' => json_encode(['publicKey' => 'existing-key'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });

        DB::table('migrations')->insert([
            [
                'migration' => '0001_01_01_000000_create_users_table',
                'batch' => 1,
            ],
            [
                'migration' => '2024_01_01_000000_create_passkeys_table',
                'batch' => 2,
            ],
            [
                'migration' => '2025_08_14_170933_add_two_factor_columns_to_users_table',
                'batch' => 3,
            ],
        ]);

        $this->assertSame(0, $this->app->make(Kernel::class)->call('migrate'));

        $this->assertDatabaseHas('users', [
            'id' => 1,
            'email' => 'existing@example.com',
            'two_factor_secret' => 'existing-secret',
        ]);
        $this->assertDatabaseHas('passkeys', [
            'id' => 1,
            'credential_id' => 'existing-credential',
        ]);
        // The language column, the super-admin flag and the role tables are new to an existing
        // install: the kept row gains the columns empty and keeps its access through `admin`.
        $this->assertSame(10, DB::table('migrations')->count());
        $this->assertDatabaseHas('users', ['id' => 1, 'lang' => null, 'is_super_admin' => false]);
        $this->assertSame(['admin'], DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', 1)
            ->pluck('roles.slug')
            ->all());
    }

    private function createExistingAuthSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->longText('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }
}
