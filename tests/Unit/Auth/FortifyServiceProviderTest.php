<?php

namespace Basics13\Tests\Unit\Auth;

use LogicException;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Basics13\Auth\FortifyServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Basics13\Tests\Feature\Auth\AuthFeatureTestCase;

/**
 * The provider only wires the limiters: the application turns them on through fortify.limiters.
 * These tests resolve each limiter the way the throttle middleware would, so the closures that
 * build the keys are exercised even when the package routes do not use them.
 */
class FortifyServiceProviderTest extends AuthFeatureTestCase
{
    public function test_login_limiter_keys_on_the_client_ip_only(): void
    {
        $limiter = RateLimiter::limiter('login');
        $this->assertNotNull($limiter);

        // The lock of each login lives in LoginAudit; this cap only stops spraying from one IP.
        $limit = $limiter(Request::create('/login', 'POST', ['email' => 'Mikel@Example.COM']));

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(10, $limit->maxAttempts);
        $this->assertSame('127.0.0.1', $limit->key);
    }

    public function test_two_factor_limiter_keys_on_the_challenged_login_id(): void
    {
        $limiter = RateLimiter::limiter('two-factor');
        $this->assertNotNull($limiter);

        $session = $this->app['session']->driver();
        $session->put('login.id', 42);

        $request = Request::create('/two-factor-challenge', 'POST');
        $request->setLaravelSession($session);

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(5, $limit->maxAttempts);
        $this->assertSame(42, $limit->key);
    }

    public function test_passkeys_limiter_keys_on_the_credential_id_when_present(): void
    {
        $limiter = RateLimiter::limiter('passkeys');
        $this->assertNotNull($limiter);

        $request = Request::create('/passkeys/login', 'POST', [
            'credential' => ['id' => 'credential-abc'],
        ]);
        $request->setLaravelSession($this->app['session']->driver());

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(10, $limit->maxAttempts);
        $this->assertSame('credential-abc|127.0.0.1', $limit->key);
    }

    public function test_passkeys_limiter_falls_back_to_the_session_id_without_a_credential(): void
    {
        $limiter = RateLimiter::limiter('passkeys');
        $this->assertNotNull($limiter);

        $sessionId = str_repeat('a', 40);
        $session = $this->app['session']->driver();
        $session->setId($sessionId);

        $request = Request::create('/passkeys/login', 'POST', ['credential' => ['id' => '']]);
        $request->setLaravelSession($session);

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame($sessionId.'|127.0.0.1', $limit->key);
    }

    public function test_it_rejects_passkey_settings_that_are_not_arrays(): void
    {
        config(['basics13.auth.passkeys' => 'invalid']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Passkey settings must be configured as arrays.');

        (new FortifyServiceProvider($this->app))->register();
    }

    public function test_it_rejects_fortify_features_that_are_not_an_array(): void
    {
        config(['fortify.features' => 'invalid']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Fortify features must be configured as an array.');

        (new FortifyServiceProvider($this->app))->register();
    }

    public function test_it_rejects_fortify_feature_names_that_are_not_strings(): void
    {
        config(['fortify.features' => [123]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Fortify feature names must be strings.');

        (new FortifyServiceProvider($this->app))->register();
    }

    public function test_it_rejects_feature_options_that_are_not_arrays(): void
    {
        config(['fortify-options.two-factor-authentication' => 'invalid']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Fortify options for [two-factor-authentication] must be configured as an array.');

        (new FortifyServiceProvider($this->app))->register();
    }

    public function test_it_rejects_feature_flags_that_are_not_booleans(): void
    {
        config(['basics13.auth.features.registration' => 'yes']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The basics13 auth feature [registration] must be configured as a boolean.');

        (new FortifyServiceProvider($this->app))->register();
    }

    public function test_it_rejects_a_users_model_that_is_not_eloquent_authenticatable(): void
    {
        config(['auth.providers.users.model' => \stdClass::class]);

        $method = new \ReflectionMethod(FortifyServiceProvider::class, 'userModelClass');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured users model must be an Eloquent authenticatable model.');

        $method->invoke(new FortifyServiceProvider($this->app));
    }
}
