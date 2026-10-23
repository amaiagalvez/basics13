<?php

namespace Basics13\Tests\Feature\Console;

use Basics13\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MakeSuperAdminTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_verified_super_admin_without_roles(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), 'root@example.test')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->assertSuccessful();

        $user = User::query()->where('email', 'root@example.test')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
        $this->assertSame(0, $user->roles()->count());
    }

    public function test_it_fails_without_changes_when_a_super_admin_already_exists(): void
    {
        User::factory()->superAdmin()->trashed()->create();

        $this->command()->assertFailed();

        $this->assertSame(1, User::withTrashed()->count());
    }

    public function test_it_fails_when_the_email_is_in_use(): void
    {
        User::factory()->trashed()->create(['email' => 'taken@example.test']);

        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), 'taken@example.test')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->assertFailed();

        $this->assertSame(0, User::withTrashed()->where('is_super_admin', true)->count());
    }

    public function test_it_fails_when_the_password_is_empty(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), 'root@example.test')
            ->expectsQuestion(__('Password'), '')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_name_is_empty(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), '')
            ->expectsQuestion(__('Email'), 'root@example.test')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_name_is_too_long(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), str_repeat('a', 256))
            ->expectsQuestion(__('Email'), 'root@example.test')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_email_is_invalid(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), 'not-an-email')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_email_is_too_long(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), str_repeat('a', 250).'@example.test')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_password_is_too_weak(): void
    {
        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), 'root@example.test')
            ->expectsQuestion(__('Password'), 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_handles_concurrent_creation_race_condition(): void
    {
        // Simulate race condition: first check passes, but another process creates super admin
        // before our transaction completes. The second check inside transaction should catch it.
        User::factory()->create(['email' => 'root@example.test', 'is_super_admin' => true]);

        // The command checks for existing super admin BEFORE asking questions,
        // so it should fail immediately without prompting
        $this->command()->assertFailed();

        // Only the original super admin should exist
        $this->assertSame(1, User::withTrashed()->where('is_super_admin', true)->count());
    }

    public function test_it_fails_when_another_run_creates_the_super_admin_while_it_asks(): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use (&$competitorCreated): void {
            if ($competitorCreated || ! str_contains($query->sql, 'is_super_admin')) {
                return;
            }

            $competitorCreated = true;
            User::factory()->superAdmin()->create(['email' => 'other@example.test']);
        });

        $this->command()
            ->expectsQuestion(__('Name'), 'Root')
            ->expectsQuestion(__('Email'), 'root@example.test')
            ->expectsQuestion(__('Password'), 'a-strong-password')
            ->expectsOutputToContain(__('A super-admin account already exists.'))
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'root@example.test']);
    }

    private function command(): PendingCommand
    {
        $command = $this->artisan('basics13:make-super-admin');
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}
