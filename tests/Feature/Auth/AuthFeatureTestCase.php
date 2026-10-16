<?php

namespace Basics13\Tests\Feature\Auth;

use Livewire\Livewire;
use Basics13\ServiceProvider;
use Flux\FluxServiceProvider;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Basics13\Tests\Feature\FeatureTestCase;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passkeys\PasskeysServiceProvider;
use Basics13\Http\Middleware\EnsureUserIsActive;
use Basics13\Auth\FortifyServiceProvider as BasicsFortifyServiceProvider;

abstract class AuthFeatureTestCase extends FeatureTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            PasskeysServiceProvider::class,
            FortifyServiceProvider::class,
            ServiceProvider::class,
            BasicsFortifyServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['fortify.home' => '/dashboard']);
        Livewire::addNamespace('basics13', __DIR__.'/../../../resources/views');
        View::addNamespace('layouts', __DIR__.'/../../Fixtures/views/layouts');
        $this->withoutVite();
        $this->app['router']->pushMiddlewareToGroup('web', EnsureUserIsActive::class);

        Route::get('/dashboard', static fn (): string => 'Dashboard')
            ->middleware(['web', 'auth'])
            ->name('dashboard');
        Route::get('/', static fn (): string => 'Home')->name('home');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
