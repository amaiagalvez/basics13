<?php

namespace Basics13\Tests\Feature\Settings;

use Basics13\Models\User;
use Basics13\Tests\Feature\Auth\AuthFeatureTestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

final class ProfilePageTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk();
        $appName = config('app.name');

        self::assertIsString($appName);
        $response->assertSeeText(__('Profile settings').' - '.$appName)
            ->assertSee('aria-labelledby="confirm-user-deletion-heading"', false)
            ->assertSee('id="confirm-user-deletion-heading"', false);
    }

    public function test_settings_root_redirects_to_the_profile_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_appearance_settings_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertSeeText(__('Appearance settings'));
    }
}
