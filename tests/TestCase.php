<?php

namespace Basics13\Tests;

use Basics13\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * Base test case for basics13 and packages that depend on it.
 *
 * Packages should extend this instead of TestbenchTestCase directly.
 */
abstract class TestCase extends TestbenchTestCase
{
    use ForbidsDatabaseInUnitTests;

    /**
     * Loads the package so its config, translations and views are available to the tests.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->forbidDatabaseInUnitTests();
    }

    /**
     * Define database migrations for the test fixtures, only for the tests that use a database.
     */
    protected function defineDatabaseMigrations(): void
    {
        if (static::usesDatabase()) {
            $this->loadMigrationsFrom(__DIR__.'/Fixtures');
        }
    }

    /**
     * Default environment setup for SQLite in-memory testing.
     * Child classes can override if they need a different database.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    /**
     * Default route definitions. Child classes can override or add more routes.
     */
    protected function defineRoutes($router): void
    {
        Route::get('login', static fn (): string => 'Login')->name('login');
        Route::get('email/verify', static fn (): string => 'Verify email')->name('verification.notice');
        Route::get('dashboard', static fn (): string => 'Dashboard')->name('dashboard');
    }
}
