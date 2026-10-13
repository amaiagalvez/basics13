<?php

namespace Basics13\Tests\Unit\Translations;

use PHPUnit\Framework\TestCase;

class TranslationFilesTest extends TestCase
{
    public function test_all_locales_define_the_same_keys(): void
    {
        $english = array_keys($this->translations('en'));
        sort($english);

        foreach (['en', 'es', 'eu', 'fr'] as $locale) {
            $keys = array_keys($this->translations($locale));
            sort($keys);

            $this->assertSame($english, $keys, "{$locale}.json keys differ from en.json.");
        }
    }

    public function test_translations_keep_the_placeholders_of_their_key(): void
    {
        foreach (['en', 'es', 'eu', 'fr'] as $locale) {
            foreach ($this->translations($locale) as $key => $value) {
                preg_match_all('/:[a-z_]+/', $key, $placeholders);
                $this->assertIsString($value);

                foreach ($placeholders[0] as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $value,
                        "{$locale}.json \"{$key}\" is missing {$placeholder}.",
                    );
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function translations(string $locale): array
    {
        $path = dirname(__DIR__, 3)."/resources/lang/{$locale}.json";
        $json = file_get_contents($path);

        if ($json === false) {
            self::fail("Cannot read {$path}.");
        }

        $translations = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($translations);

        return $translations;
    }
}
