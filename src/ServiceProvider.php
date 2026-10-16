<?php

namespace Basics13;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->authorizeLogViewer();

        $viewsPath = __DIR__.'/../resources/views';
        $publishedViewsPath = resource_path('views/vendor/basics13');

        $this->loadViewsFrom($viewsPath, 'basics13');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'basics13');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/settings.php');

        Blade::anonymousComponentNamespace('basics13::components', 'basics13');
        Blade::anonymousComponentNamespace('basics13::layouts', 'basics13-layouts');
        Blade::anonymousComponentNamespace('basics13::pages', 'basics13-pages');

        $this->app->booted(function () use ($publishedViewsPath, $viewsPath): void {
            Livewire::addNamespace(
                'basics13',
                is_dir($publishedViewsPath) ? $publishedViewsPath : $viewsPath,
            );
        });

        $this->publishes([
            __DIR__.'/../config/basics13.php' => config_path('basics13.php'),
        ], 'basics13-config');

        $this->publishes([
            $viewsPath => $publishedViewsPath,
        ], 'basics13-auth-views');

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/basics13'),
            __DIR__.'/../resources/css' => resource_path('css/vendor/basics13'),
        ], 'basics13-assets');

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
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/basics13.php', 'basics13');
    }

    protected function authorizeLogViewer(): void
    {
        Gate::define(
            'viewLogViewer',
            static function (Model $user): bool {
                $allowedEmails = config('log-viewer.allowed_emails', []);
                $email = $user->getAttribute('email');

                return is_string($email) && is_array($allowedEmails) && in_array($email, $allowedEmails, true);
            },
        );
        Gate::define('downloadLogFile', fn (): bool => false);
        Gate::define('downloadLogFolder', fn (): bool => false);
        Gate::define('deleteLogFile', fn (): bool => false);
        Gate::define('deleteLogFolder', fn (): bool => false);
    }
}
