<?php

namespace Basics13\Tests;

use Basics13\ServiceProvider;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    /**
     * Loads the package so its config, translations and views are available to the tests.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TranslationServiceProvider::class,
            ValidationServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    /**
     * The tests run on SQLite in memory, so nobody needs a database server to check the package.
     * The engines the application really runs are that application's own business.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->app !== null) {
            $this->app->setLocale('en');

            // Ensure translator is available for __() helper
            $this->app->booted(function ($app) {
                if (! $app->bound('translator')) {
                    $loader = $app->make('translation.loader');
                    $locale = $app->getLocale();
                    $app->instance('translator', new Translator($loader, $locale));
                }
            });
        }
    }

    /**
     * Define database migrations for the test fixtures.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures');
    }
}
