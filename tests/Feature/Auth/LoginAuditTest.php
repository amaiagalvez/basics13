<?php

namespace Basics13\Tests\Feature\Auth;

use Basics13\Models\User;
use Basics13\Auth\LoginAudit;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Basics13\Enums\LoginFailureReason;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class LoginAuditTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    public function test_a_successful_login_is_recorded_and_linked_to_the_regenerated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $row = $this->onlyRow(SessionEvent::Login);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame($user->email, $row->login);
        $this->assertSame('127.0.0.1', $row->ip);
        $this->assertNotNull($row->session_key);
        $this->assertNull($row->failure_reason);

        // Fortify regenerates the session at login; the key migrates with its data.
        $response->assertSessionHas(LoginAudit::SESSION_KEY, $row->session_key);
    }

    public function test_a_wrong_password_is_recorded_with_the_user(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $row = $this->onlyRow(SessionEvent::Failed);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(LoginFailureReason::WrongPassword, $row->failure_reason);
        $this->assertSame($user->email, $row->login);
    }

    public function test_an_unknown_login_is_recorded_without_a_user(): void
    {
        $this->post(route('login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $row = $this->onlyRow(SessionEvent::Failed);
        $this->assertNull($row->user_id);
        $this->assertSame(LoginFailureReason::UnknownUser, $row->failure_reason);
        $this->assertSame('nobody@example.com', $row->login);
    }

    public function test_an_inactive_account_is_recorded_with_its_reason(): void
    {
        $user = User::factory()->archived()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $row = $this->onlyRow(SessionEvent::Failed);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(LoginFailureReason::Inactive, $row->failure_reason);
    }

    public function test_no_column_holds_the_typed_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'super-secret-wrong-password',
        ]);

        $row = $this->onlyRow(SessionEvent::Failed);

        foreach ($row->getAttributes() as $column => $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('super-secret-wrong-password', $value, $column);
            }
        }
    }

    public function test_the_logout_stamps_only_the_row_of_its_own_session(): void
    {
        $user = User::factory()->create();
        $otherLogin = SessionLog::factory()->login()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $ownLogin = $this->onlyRow(SessionEvent::Login, ignore: [$otherLogin->id]);

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertNotNull($ownLogin->refresh()->logged_out_at);
        $this->assertNull($otherLogin->refresh()->logged_out_at);
    }

    public function test_a_repeated_logout_neither_fails_nor_changes_anything(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $row = $this->onlyRow(SessionEvent::Login)->refresh();
        $loggedOutAt = $row->logged_out_at;

        // The other tab asks for logout with the session already invalidated: a guest now.
        $this->post(route('logout'))->assertRedirect(route('login'));

        // A logout carrying the same key again, later, leaves the first stamp in place.
        $this->travel(5)->minutes();
        $this->actingAs($user)
            ->withSession([LoginAudit::SESSION_KEY => $row->session_key])
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertEquals($loggedOutAt, $this->onlyRow(SessionEvent::Login)->refresh()->logged_out_at);
        $this->assertSame(1, SessionLog::query()->count());
    }

    public function test_a_logout_without_a_session_key_does_not_fail(): void
    {
        $user = User::factory()->create();

        // actingAs skips the login flow, so the session has no key.
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertSame(0, SessionLog::query()->count());
    }

    public function test_restoring_the_session_with_the_remember_cookie_creates_no_row(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);
        $this->assertAuthenticatedAs($user);

        $recaller = collect($response->headers->getCookies())->first(
            static fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_web_'),
        );
        $this->assertNotNull($recaller, 'The remember cookie must be queued.');

        // A new browser session: the guard is dropped and only the recaller cookie comes back.
        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, SessionLog::query()->where('event', SessionEvent::Login->value)->count());
    }

    public function test_impersonating_and_leaving_create_no_rows(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.impersonate', $target))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($target);

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('users.index', absolute: false));
        $this->assertAuthenticatedAs($admin);

        $this->assertSame(0, SessionLog::query()->count());
    }

    /**
     * @param  list<int>  $ignore
     */
    private function onlyRow(SessionEvent $event, array $ignore = []): SessionLog
    {
        $rows = SessionLog::query()->whereNotIn('id', $ignore)->get();

        $this->assertCount(1, $rows);
        $this->assertSame($event, $rows->first()->event);

        return $rows->first();
    }
}
