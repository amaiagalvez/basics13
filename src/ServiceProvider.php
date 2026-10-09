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

        // pint.json is published from the package root: the package and its
        // applications share the same formatting rules.
        $this->publishes([
            __DIR__.'/../pint.json' => base_path('pint.json'),
            __DIR__.'/../tooling/phpstan.neon' => base_path('phpstan.neon'),
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
