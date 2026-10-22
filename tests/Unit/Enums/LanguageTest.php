<?php

namespace Basics13\Tests\Unit\Enums;

use Basics13\Enums\Language;
use Basics13\Tests\TestCase;

class LanguageTest extends TestCase
{
    public function test_the_codes_are_the_translated_locales(): void
    {
        $this->assertSame(['eu', 'es', 'fr', 'en'], Language::codes());
    }

    public function test_the_options_name_each_language_natively(): void
    {
        $this->assertSame([
            'eu' => 'Euskara',
            'es' => 'Español',
            'fr' => 'Français',
            'en' => 'English',
        ], Language::options());
    }
}
