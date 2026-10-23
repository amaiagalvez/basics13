<?php

namespace Basics13\Tests\Feature\Configuration;

use Basics13\Models\Setting;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingGetTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_the_default_applies_when_the_variable_is_missing(): void
    {
        $this->assertSame(3, Setting::get('basics13', 'missing_variable', 3));
        $this->assertSame('fallback', Setting::get('basics13', 'missing_variable', 'fallback'));
    }

    public function test_the_default_applies_when_the_value_is_empty(): void
    {
        Setting::factory()->create(['variable' => 'empty_string', 'value' => '']);
        Setting::factory()->create(['variable' => 'null_string', 'value' => null]);
        Setting::factory()->integer(5)->create(['variable' => 'empty_integer', 'value' => '  ']);

        $this->assertSame('fallback', Setting::get('basics13', 'empty_string', 'fallback'));
        $this->assertSame('fallback', Setting::get('basics13', 'null_string', 'fallback'));
        $this->assertSame(15, Setting::get('basics13', 'empty_integer', 15));
    }

    public function test_an_integer_value_is_cast_and_bounded(): void
    {
        Setting::factory()->integer(5)->create(['variable' => 'valid_integer']);
        Setting::factory()->integer(0)->create(['variable' => 'zero_integer']);
        Setting::factory()->integer()->create(['variable' => 'not_an_integer', 'value' => 'abc']);
        Setting::factory()->integer(Setting::MAX_INTEGER)->create(['variable' => 'max_integer']);
        Setting::factory()->integer(Setting::MAX_INTEGER + 1)->create(['variable' => 'above_max_integer']);

        $this->assertSame(5, Setting::get('basics13', 'valid_integer', 3));
        $this->assertSame(3, Setting::get('basics13', 'zero_integer', 3));
        $this->assertSame(3, Setting::get('basics13', 'not_an_integer', 3));
        $this->assertSame(Setting::MAX_INTEGER, Setting::get('basics13', 'max_integer', 3));
        $this->assertSame(3, Setting::get('basics13', 'above_max_integer', 3));
    }

    public function test_a_changed_value_applies_on_the_next_read(): void
    {
        // The row seeded by the package migration.
        $setting = Setting::query()->where('package', 'basics13')->where('variable', 'max_login_attempts')->sole();

        $this->assertSame(3, Setting::get('basics13', 'max_login_attempts', 1));

        $setting->value = '5';
        $setting->save();

        $this->assertSame(5, Setting::get('basics13', 'max_login_attempts', 1));
    }
}
