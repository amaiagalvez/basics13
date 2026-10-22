<?php

namespace Basics13\Tests\Feature;

use Basics13\Models\User;
use Basics13\ServiceProvider;
use Flux\FluxServiceProvider;
use Livewire\LivewireServiceProvider;
use Basics13\Tests\ForbidsDatabaseInUnitTests;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class FeatureTestCase extends TestbenchTestCase
{
    use ForbidsDatabaseInUnitTests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->forbidDatabaseInUnitTests();
    }

    /**
     * The tests run on SQLite in memory, so nobody needs a database server to check the package.
     * The engines the application really runs are that application's own business.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', str_repeat('a', 32));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * Loads the package so its config, translations and views are available to the tests.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            ServiceProvider::class,
        ];
    }
}
