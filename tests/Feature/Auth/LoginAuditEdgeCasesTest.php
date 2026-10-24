<?php

namespace Basics13\Tests\Feature\Auth;

use RuntimeException;
use Basics13\Models\User;
use Basics13\Models\Setting;
use Basics13\Auth\LoginAudit;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

/**
 * The listeners outside the sign-in form: a challenge step without the login field, requests
 * without a session and users that are not Eloquent models.
 */
class LoginAuditEdgeCasesTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    public function test_a_login_without_the_typed_field_records_the_account_login(): void
    {
        $user = User::factory()->create(['email' => 'challenge@example.com']);
        request()->setLaravelSession($this->app['session']->driver());

        event(new Login('web', $user, false));

        $this->assertSame('challenge@example.com', SessionLog::query()->sole()->login);
    }

    public function test_a_login_outside_a_session_records_nothing(): void
    {
        event(new Login('web', User::factory()->create(), false));

        $this->assertSame(0, SessionLog::query()->count());
    }

    public function test_a_user_that_is_not_a_model_records_nothing(): void
    {
        request()->setLaravelSession($this->app['session']->driver());

        event(new Login('web', new GenericUser(['id' => 1]), false));

        $this->assertSame(0, SessionLog::query()->count());
    }

    public function test_an_inactive_account_that_signs_in_records_nothing(): void
    {
        request()->setLaravelSession($this->app['session']->driver());

        // The active-users listener throws first; LoginAudit must not rely on that order.
        app(LoginAudit::class)->handleLogin(new Login('web', User::factory()->archived()->create(), false));

        $this->assertSame(0, SessionLog::query()->count());
    }

    public function test_a_lock_set_by_a_concurrent_request_is_not_recorded_twice(): void
    {
        Setting::query()->where('package', 'basics13')->where('variable', 'max_login_attempts')->update(['value' => '1']);
        request()->merge(['email' => 'race@example.com']);
        // The other request set the lock between this one's password check and its failure.
        Cache::put('login-locked|race@example.com', now()->getTimestamp() + 900, 900);

        event(new Failed('web', null, ['email' => 'race@example.com']));

        $this->assertSame(1, SessionLog::query()->where('event', SessionEvent::Failed->value)->count());
        $this->assertSame(0, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
    }

    public function test_an_alert_raised_by_a_concurrent_request_holds_this_one_back(): void
    {
        Exceptions::fake();
        SessionLog::factory()->count(10)->create();
        request()->merge(['email' => 'spike@example.com']);
        // The limiter keeps its own store; resolve it before the facade is replaced.
        RateLimiter::attempts('spike@example.com');
        // No alert was there when this request looked, but the other one stored it first.
        Cache::shouldReceive('has')->with(LoginAudit::ALERT_KEY)->andReturnFalse();
        Cache::shouldReceive('add')->with(LoginAudit::ALERT_KEY, true, LoginAudit::ALERT_MINUTES * 60)->once()->andReturnFalse();

        event(new Failed('web', null, ['email' => 'spike@example.com']));

        Exceptions::assertNothingReported();
    }

    public function test_an_alert_mail_that_cannot_be_sent_is_reported_without_breaking_the_sign_in(): void
    {
        Exceptions::fake();
        Setting::query()->where('package', 'basics13')->where('variable', 'alert_email')->update(['value' => 'security@example.com']);
        SessionLog::factory()->count(10)->create();
        Mail::shouldReceive('mailer')->andReturnSelf();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail server down'));

        $this->post(route('login.store'), ['email' => 'spike@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        Exceptions::assertReportedCount(2);
        Exceptions::assertReported(static fn (RuntimeException $exception): bool => $exception->getMessage() === 'Mail server down');
    }

    public function test_a_logout_outside_a_session_does_nothing(): void
    {
        $login = SessionLog::factory()->login()->create();

        event(new Logout('web', User::query()->whereKey($login->user_id)->sole()));

        $this->assertNull($login->refresh()->logged_out_at);
    }
}
