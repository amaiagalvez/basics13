<?php

namespace Basics13;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'basics13');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'basics13');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang');

        $this->publishes([
            __DIR__.'/../config/basics13.php' => config_path('basics13.php'),
        ], 'basics13-config');

        // pint.json is published from the package root (the package and its
        // applications share the same rules); phpstan.neon and phpunit.xml live
        // in tooling/ because the package needs its own versions to analyse and
        // test itself. Everything else an application needs to run lives only in
        // tooling/ and is published from there.
        $this->publishes([
            __DIR__.'/../pint.json' => base_path('pint.json'),
            __DIR__.'/../tooling/phpstan.neon' => base_path('phpstan.neon'),
            __DIR__.'/../tooling/phpunit.xml' => base_path('phpunit.xml'),
            __DIR__.'/../tooling/phpunit.dusk.xml' => base_path('phpunit.dusk.xml'),
            __DIR__.'/../tooling/boost.json' => base_path('boost.json'),
            __DIR__.'/../tooling/docker-compose.yml' => base_path('docker-compose.yml'),
            __DIR__.'/../tooling/Dockerfile.dusk' => base_path('Dockerfile.dusk'),
        ], 'basics13-tooling');

        // $this->publishes([
        //     __DIR__ . '/../resources/lang' => lang_path('vendor/basics13'),
        // ], 'basics13-lang');

        // $this->publishes([
        //     __DIR__ . '/../resources/views' => resource_path('views/vendor/basics13'),
        // ], 'basics13-views');

        // $this->publishes([
        //     __DIR__ . '/../resources/views/vendor/pagination' => resource_path('views/vendor/pagination'),
        // ], 'basics13-pagination');

        $this->mergeConfigFrom(__DIR__.'/../config/basics13.php', 'basics13');
    }

    public function register(): void
    {
        //
    }
}
