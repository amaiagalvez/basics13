<?php

namespace Basics13\Tests\Feature\Users;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Basics13\Tests\Feature\FeatureTestCase;

/**
 * Base for the account-resource tests.
 *
 * The resource views render the host layout and the transformer links back to the dashboard, so the
 * two host pieces the package expects are provided here: the shared stand-in layout of the feature
 * tests, and the routes the views and redirects point at.
 */
abstract class UserTestCase extends FeatureTestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('login', static fn (): string => 'Login')->name('login');
        Route::get('email/verify', static fn (): string => 'Verify email')->name('verification.notice');
        Route::get('dashboard', static fn (): string => 'Dashboard')->name('dashboard');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Blade::anonymousComponentPath(__DIR__.'/../../Fixtures/views/layouts', 'layouts');
    }
}
