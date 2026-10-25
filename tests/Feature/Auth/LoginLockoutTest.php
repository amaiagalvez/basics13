<?php

namespace Basics13\Tests\Feature\Auth;

use Basics13\Models\User;
use Basics13\Models\Setting;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Illuminate\Testing\TestResponse;
use Basics13\Enums\LoginFailureReason;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class LoginLockoutTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    public function test_the_third_spaced_failure_from_different_ips_locks_the_login_for_full_minutes(): void
    {
        $user = User::factory()->create(['email' => 'locked@example.com']);

        $this->attempt('locked@example.com', 'wrong', '10.0.0.1');
        $this->travel(1)->minutes();
        $this->attempt('LOCKED@example.com', 'wrong', '10.0.0.2');
        $this->travel(13)->minutes();
        $this->attempt('locked@example.com', 'wrong', '10.0.0.3');

        $lockout = SessionLog::query()->where('event', SessionEvent::Lockout->value)->sole();
        $this->assertSame($user->id, $lockout->user_id);
        $this->assertSame(15, $lockout->lockout_minutes);

        // Minute 28: still locked, even with the right password, and the lock is not extended.
        $this->travel(14)->minutes();
        $this->attempt('locked@example.com', 'password', '10.0.0.4')
            ->assertSessionHasErrors(['email' => __('basics13::messages.login_locked', ['minutes' => 1])]);
        $this->assertGuest();

        $locked = SessionLog::query()->where('failure_reason', LoginFailureReason::Locked->value)->sole();
        $this->assertSame($user->id, $locked->user_id);
        $this->assertSame(1, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());

        // Minute 30: the lock has expired.
        $this->travel(2)->minutes();
        $this->attempt('locked@example.com', 'password', '10.0.0.4')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_unknown_login_is_locked_too(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->attempt('ghost@example.com', 'wrong');
        }

        $lockout = SessionLog::query()->where('event', SessionEvent::Lockout->value)->sole();
        $this->assertNull($lockout->user_id);

        $this->attempt('ghost@example.com', 'wrong')
            ->assertSessionHasErrors(['email' => __('basics13::messages.login_locked', ['minutes' => 15])]);
    }

    public function test_failures_older_than_the_lockout_window_do_not_count(): void
    {
        User::factory()->create(['email' => 'slow@example.com']);

        $this->attempt('slow@example.com', 'wrong');
        $this->attempt('slow@example.com', 'wrong');
        $this->travel(16)->minutes();
        $this->attempt('slow@example.com', 'wrong');

        $this->assertSame(0, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
    }

    public function test_a_successful_login_resets_the_counter(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->attempt('reset@example.com', 'wrong');
        $this->attempt('reset@example.com', 'wrong');
        $this->attempt('reset@example.com', 'password');
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        $this->attempt('reset@example.com', 'wrong');
        $this->attempt('reset@example.com', 'wrong');

        $this->assertSame(0, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
    }

    public function test_a_maximum_changed_from_the_configuration_screen_applies_to_the_next_sign_in(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create())
            ->put(route('configuration.update', 'basics13'), ['values' => [
                'max_login_attempts' => '5', 'lockout_minutes' => '15', 'alert_email' => '', 'languages' => ['eu'],
            ]])
            ->assertSessionHasNoErrors();
        $this->newBrowserSession();

        foreach (range(1, 4) as $attempt) {
            $this->attempt('five@example.com', 'wrong', '10.0.2.'.$attempt);
        }
        $this->assertSame(0, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());

        $this->attempt('five@example.com', 'wrong', '10.0.2.5');
        $this->assertSame(1, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
    }

    public function test_lowering_the_maximum_with_a_running_counter_writes_a_single_lockout(): void
    {
        $this->attempt('lower@example.com', 'wrong');
        $this->attempt('lower@example.com', 'wrong');

        Setting::query()->where('package', 'basics13')->where('variable', 'max_login_attempts')->update(['value' => '1']);

        $this->attempt('lower@example.com', 'wrong');
        $this->attempt('lower@example.com', 'wrong');

        $this->assertSame(1, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
        $this->assertSame(1, SessionLog::query()->where('failure_reason', LoginFailureReason::Locked->value)->count());
    }

    public function test_the_lockout_minutes_setting_applies(): void
    {
        Setting::query()->where('package', 'basics13')->where('variable', 'lockout_minutes')->update(['value' => '5']);

        foreach (range(1, 3) as $attempt) {
            $this->attempt('short@example.com', 'wrong');
        }

        $this->assertSame(5, SessionLog::query()->where('event', SessionEvent::Lockout->value)->sole()->lockout_minutes);
        $this->travel(4)->minutes();
        $this->attempt('short@example.com', 'wrong')->assertSessionHasErrors(['email' => __('basics13::messages.login_locked', ['minutes' => 1])]);

        $this->travel(2)->minutes();
        $this->attempt('short@example.com', 'wrong')->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_restoring_with_the_remember_cookie_does_not_reset_the_counter(): void
    {
        $user = User::factory()->create(['email' => 'remember@example.com']);

        $response = $this->attempt('remember@example.com', 'password', remember: true);
        $recaller = collect($response->headers->getCookies())->first(
            static fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_web_'),
        );
        $this->assertNotNull($recaller);
        $this->newBrowserSession();

        $this->attempt('remember@example.com', 'wrong');
        $this->attempt('remember@example.com', 'wrong');

        $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->newBrowserSession();

        $this->attempt('remember@example.com', 'wrong');

        $this->assertSame(1, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
    }

    public function test_impersonating_and_leaving_do_not_reset_the_counter_of_either_account(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'admin@example.com']);
        User::factory()->create(['email' => 'target@example.com']);

        $this->attempt('target@example.com', 'wrong');
        $this->attempt('target@example.com', 'wrong');
        $this->attempt('admin@example.com', 'wrong');
        $this->attempt('admin@example.com', 'wrong');

        $this->actingAs($admin)->post(route('users.impersonate', User::query()->where('email', 'target@example.com')->sole()));
        $this->post(route('impersonation.leave'));
        $this->newBrowserSession();

        $this->attempt('target@example.com', 'wrong');
        $this->attempt('admin@example.com', 'wrong');

        $this->assertSame(2, SessionLog::query()->where('event', SessionEvent::Lockout->value)->count());
    }

    /**
     * @return TestResponse<Response>
     */
    private function attempt(string $login, string $password, string $ip = '127.0.0.1', bool $remember = false): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('login.store'), array_filter([
            'email' => $login,
            'password' => $password,
            'remember' => $remember ?: null,
        ]));
    }

    /**
     * A fresh browser: nobody signed in, no cookies and no session data left from the previous
     * requests.
     */
    private function newBrowserSession(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }
}
