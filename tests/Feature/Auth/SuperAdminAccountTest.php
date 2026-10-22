<?php

namespace Basics13\Tests\Feature\Auth;

use Livewire\Livewire;
use Basics13\Models\User;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Nothing stops the super-admin from saving its own account: signing in with "remember me",
 * changing the password and confirming 2FA all write the user row.
 */
class SuperAdminAccountTest extends AuthFeatureTestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_signs_in_with_remember_me_and_signs_out(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['remember_token' => null]);

        $this->post(route('login.store'), [
            'email' => $superAdmin->email,
            'password' => 'password',
            'remember' => 'on',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertNotNull($superAdmin->refresh()->remember_token);

        $this->post(route('logout'))->assertRedirect();

        $this->assertGuest();
        $this->assertTrue($superAdmin->refresh()->is_super_admin);
    }

    public function test_the_super_admin_changes_its_password(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['password' => Hash::make('password')]);
        $this->actingAs($superAdmin);

        Livewire::test('basics13::pages.settings.security')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $superAdmin->refresh()->password));
    }

    public function test_the_super_admin_confirms_two_factor_authentication(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false]);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $component = Livewire::test('basics13::pages.settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->call('startTwoFactorSetup');

        $secret = decrypt((string) $superAdmin->refresh()->two_factor_secret);

        $component->set('code', app(Google2FA::class)->getCurrentOtp($secret))
            ->call('confirmTwoFactor')
            ->assertHasNoErrors();

        $this->assertNotNull($superAdmin->refresh()->two_factor_confirmed_at);
    }
}
