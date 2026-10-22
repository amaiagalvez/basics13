<?php

namespace Basics13\Tests\Feature\Http\Middleware;

use Basics13\Models\User;
use Basics13\Enums\Language;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Basics13\Http\Middleware\SetUserLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Basics13\Tests\Feature\Auth\AuthFeatureTestCase;

class SetUserLocaleTest extends AuthFeatureTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'es']);
        $this->app['router']->pushMiddlewareToGroup('web', SetUserLocale::class);

        Route::get('/locale', static fn (): string => App::getLocale())->middleware(['web', 'auth']);
    }

    public function test_the_interface_uses_the_user_language(): void
    {
        $this->actingAs(User::factory()->language(Language::EU)->create())
            ->get('/locale')
            ->assertOk()
            ->assertContent('eu');
    }

    public function test_a_user_without_language_gets_the_application_locale(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/locale')
            ->assertOk()
            ->assertContent('es');
    }

    public function test_an_unsupported_stored_code_falls_back_to_the_application_locale(): void
    {
        $this->actingAs(User::factory()->create(['lang' => 'de']))
            ->get('/locale')
            ->assertOk()
            ->assertContent('es');
    }
}
