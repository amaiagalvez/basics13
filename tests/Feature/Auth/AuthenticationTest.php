<?php

namespace Basics13\Tests\Feature\Auth;

use Basics13\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class AuthenticationTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_per_ip_after_ten_attempts(): void
    {
        // A different login each time: the cap is per IP, whatever login is tried.
        foreach (range(1, 10) as $attempt) {
            $this->post(route('login.store'), [
                'email' => "spray{$attempt}@example.com",
                'password' => 'wrong-password',
            ])->assertSessionHasErrorsIn('email');
        }

        $this->postJson(route('login.store'), [
            'email' => 'spray11@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        // Another IP is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post(route('login.store'), [
                'email' => 'spray12@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
