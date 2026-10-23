<?php

namespace Basics13\Tests\Feature\Configuration;

use Basics13\Models\User;
use Basics13\Models\Setting;
use Basics13\Enums\SettingType;
use Basics13\Tests\Feature\Roles\RoleTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The configuration screen reuses the role tests' stand-in layout and host routes.
 */
class SettingScreenTest extends RoleTestCase
{
    use RefreshDatabase;

    public function test_the_screen_shows_the_package_variables_with_their_labels(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create());

        $this->get(route('configuration.index'))
            ->assertOk()
            ->assertViewHas('package', 'basics13')
            ->assertSee(__('Application configuration'))
            ->assertSee('Maximum login attempts')
            ->assertSee('data-test="configuration-submit"', false);
    }

    public function test_an_unknown_package_is_not_found(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.manage'])->create());

        $this->get(route('configuration.index', ['package' => 'nowhere13']))->assertNotFound();
        $this->put(route('configuration.update', 'nowhere13'), ['values' => ['x' => '1']])->assertNotFound();
    }

    public function test_a_valid_save_changes_only_the_values(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create());

        $this->put(route('configuration.update', 'basics13'), ['values' => [
            'max_login_attempts' => '5',
            'lockout_minutes' => '30',
            'alert_email' => 'security@example.com',
        ]])->assertRedirect(route('configuration.index', ['package' => 'basics13']))->assertSessionHasNoErrors();

        $this->assertSame(5, Setting::get('basics13', 'max_login_attempts', 3));
        $this->assertSame(30, Setting::get('basics13', 'lockout_minutes', 15));
        $this->assertSame('security@example.com', Setting::get('basics13', 'alert_email', ''));
    }

    public function test_zero_or_a_non_integer_is_rejected_and_the_previous_value_stays(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create());

        foreach (['0', 'abc', '1.5', (string) (Setting::MAX_INTEGER + 1)] as $invalid) {
            $this->put(route('configuration.update', 'basics13'), ['values' => [
                'max_login_attempts' => '5',
                'lockout_minutes' => $invalid,
                'alert_email' => '',
            ]])->assertSessionHasErrors('values.lockout_minutes');
        }

        $this->assertSame(15, Setting::get('basics13', 'lockout_minutes', 1));
        $this->assertSame(3, Setting::get('basics13', 'max_login_attempts', 1));
    }

    public function test_an_empty_alert_email_is_valid_and_an_invalid_one_is_not(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create());

        $this->put(route('configuration.update', 'basics13'), ['values' => [
            'max_login_attempts' => '3', 'lockout_minutes' => '15', 'alert_email' => '',
        ]])->assertSessionHasNoErrors();

        $this->put(route('configuration.update', 'basics13'), ['values' => [
            'max_login_attempts' => '3', 'lockout_minutes' => '15', 'alert_email' => 'not-an-email',
        ]])->assertSessionHasErrors('values.alert_email');
    }

    public function test_a_text_variable_takes_any_bounded_text(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create());
        Setting::factory()->create(['package' => 'basics13', 'variable' => 'greeting', 'type' => SettingType::String, 'value' => 'Hi']);

        $this->put(route('configuration.update', 'basics13'), ['values' => [
            'max_login_attempts' => '3', 'lockout_minutes' => '15', 'alert_email' => '', 'greeting' => 'Kaixo',
        ]])->assertSessionHasNoErrors();
        $this->assertSame('Kaixo', Setting::get('basics13', 'greeting', ''));

        $this->put(route('configuration.update', 'basics13'), ['values' => [
            'max_login_attempts' => '3', 'lockout_minutes' => '15', 'alert_email' => '',
            'greeting' => str_repeat('a', (int) config('basics13.validation.max_length.string') + 1),
        ]])->assertSessionHasErrors('values.greeting');
    }

    public function test_type_and_names_cannot_be_changed_from_the_request(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view', 'settings.edit'])->create());

        $this->put(route('configuration.update', 'basics13'), [
            'values' => ['max_login_attempts' => '4', 'lockout_minutes' => '15', 'alert_email' => ''],
            'type' => ['max_login_attempts' => 'string'],
            'name_en' => ['max_login_attempts' => 'Hacked'],
            'max_login_attempts' => ['type' => 'string', 'name_en' => 'Hacked'],
        ])->assertSessionHasNoErrors();

        $setting = Setting::query()->where('package', 'basics13')->where('variable', 'max_login_attempts')->sole();
        $this->assertSame(SettingType::Integer, $setting->type);
        $this->assertSame('Maximum login attempts', $setting->name_en);
        $this->assertSame('4', $setting->value);
    }

    public function test_view_only_shows_disabled_fields_without_save_and_denies_the_save(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view'])->create());

        $this->get(route('configuration.index'))
            ->assertOk()
            ->assertViewHas('canEdit', false)
            ->assertDontSee('data-test="configuration-submit"', false);

        $this->put(route('configuration.update', 'basics13'), ['values' => [
            'max_login_attempts' => '9', 'lockout_minutes' => '15', 'alert_email' => '',
        ]])->assertForbidden();

        $this->assertSame(3, Setting::get('basics13', 'max_login_attempts', 1));
    }

    public function test_without_permissions_both_are_denied(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view'])->create());

        $this->get(route('configuration.index'))->assertForbidden();
        $this->put(route('configuration.update', 'basics13'), ['values' => ['max_login_attempts' => '9']])->assertForbidden();
    }
}
