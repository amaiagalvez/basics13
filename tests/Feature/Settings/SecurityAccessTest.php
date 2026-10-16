<?php

namespace Basics13\Tests\Feature\Settings;

use Basics13\Models\User;
use Laravel\Fortify\Features;
use Basics13\Http\Middleware\EnsureUserIsActive;
use Basics13\Tests\Feature\Auth\AuthFeatureTestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class SecurityAccessTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
    }

    /* @chisel-password-confirmation */
    public function test_security_settings_page_requires_password_confirmation_when_enabled(): void
    {
        $this->withoutMiddleware(EnsureUserIsActive::class)
            ->actingAs(User::factory()->make()->forceFill(['id' => 1]))
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }
    /* @end-chisel-password-confirmation */

    public function test_passkey_endpoint_discovery_is_public_and_points_to_security_settings(): void
    {
        $this->getJson(route('well-known.passkeys'))
            ->assertOk()
            ->assertExactJson([
                'enroll' => route('security.edit'),
                'manage' => route('security.edit'),
            ]);
    }
}
