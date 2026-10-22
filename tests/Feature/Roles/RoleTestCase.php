<?php

namespace Basics13\Tests\Feature\Roles;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Basics13\Tests\Feature\FeatureTestCase;

/**
 * Base for the role-resource tests: the stand-in host layout and the routes the views and the
 * redirects point at.
 */
abstract class RoleTestCase extends FeatureTestCase
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
