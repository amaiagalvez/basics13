<?php

namespace Basics13\Tests\Unit\Models;

use Basics13\Models\Setting;
use Basics13\Tests\TestCase;
use Basics13\Enums\SettingType;

class SettingTest extends TestCase
{
    public function test_the_type_is_cast_to_the_enum(): void
    {
        $this->assertSame(SettingType::Integer, (new Setting(['type' => 'integer']))->type);
        $this->assertSame(SettingType::String, (new Setting(['type' => 'string']))->type);
        $this->assertSame(SettingType::Email, (new Setting(['type' => 'email']))->type);
    }

    public function test_the_label_uses_the_interface_language(): void
    {
        $setting = new Setting([
            'name_eu' => 'Saiaera gehienez',
            'name_es' => 'Intentos máximos',
            'name_fr' => 'Tentatives maximales',
            'name_en' => 'Maximum attempts',
        ]);

        app()->setLocale('eu');
        $this->assertSame('Saiaera gehienez', $setting->label());

        app()->setLocale('es');
        $this->assertSame('Intentos máximos', $setting->label());
    }

    public function test_the_label_falls_back_to_english(): void
    {
        $setting = new Setting([
            'name_eu' => '',
            'name_en' => 'Maximum attempts',
        ]);

        app()->setLocale('eu');
        $basque = $setting->label();
        app()->setLocale('de');
        $german = $setting->label();

        $this->assertSame(['Maximum attempts', 'Maximum attempts'], [$basque, $german]);
    }

    public function test_the_description_uses_the_same_fallback(): void
    {
        $setting = new Setting([
            'description_es' => '',
            'description_en' => 'Attempts before the login is locked.',
        ]);

        app()->setLocale('es');
        $this->assertSame('Attempts before the login is locked.', $setting->description());
    }
}
