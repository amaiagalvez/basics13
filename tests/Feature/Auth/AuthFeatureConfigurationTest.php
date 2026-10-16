<?php

namespace Basics13\Tests\Feature\Auth;

use Basics13\ServiceProvider;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Basics13\Tests\Feature\FeatureTestCase;
use Basics13\Http\Middleware\RequirePasswordForLivewire;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Basics13\Auth\FortifyServiceProvider as BasicsFortifyServiceProvider;
use Laravel\Fortify\FortifyServiceProvider as LaravelFortifyServiceProvider;

final class AuthFeatureConfigurationTest extends FeatureTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            LaravelFortifyServiceProvider::class,
            ServiceProvider::class,
            DisabledAuthFeaturesServiceProvider::class,
            BasicsFortifyServiceProvider::class,
        ];
    }

    public function test_disabled_features_are_not_exposed_by_fortify_or_account_routes(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Features::enabled(Features::emailVerification()));
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::enabled(Features::passkeys()));
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('verification.notice'));
        $this->assertFalse(Route::has('two-factor.login'));
        $this->assertFalse(Route::has('well-known.passkeys'));

        $securityRoute = Route::getRoutes()->getByName('security.edit');

        $this->assertNotNull($securityRoute);
        $this->assertNotContains(RequirePasswordForLivewire::class, $securityRoute->gatherMiddleware());
    }
}

final class DisabledAuthFeaturesServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        config(['basics13.auth.features' => [
            'registration' => false,
            'email_verification' => false,
            'two_factor_authentication' => false,
            'passkeys' => false,
            'password_confirmation' => false,
        ]]);
    }
}
