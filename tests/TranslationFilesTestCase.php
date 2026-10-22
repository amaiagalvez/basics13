<?php

namespace Basics13\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every package ships the same locales with the same keys, and a translation keeps the
 * placeholders of its source. Each package extends this and points it at its own root.
 */
abstract class TranslationFilesTestCase extends TestCase
{
    /** @var list<string> */
    private const LOCALES = ['en', 'es', 'eu', 'fr'];

    private const PLACEHOLDER = '/:[a-z_]+/i';

    abstract protected function packageRoot(): string;

    public function test_all_locales_define_the_same_keys(): void
    {
        $english = array_keys($this->jsonTranslations('en'));
        sort($english);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->jsonTranslations($locale));
            sort($keys);

            $this->assertSame($english, $keys, "{$locale}.json keys differ from en.json.");
        }
    }

    public function test_translations_keep_the_placeholders_of_their_key(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->jsonTranslations($locale) as $key => $value) {
                $this->assertIsString($value);
                $this->assertKeepsPlaceholders($key, $value, "{$locale}.json \"{$key}\"");
            }
        }
    }

    public function test_all_locales_ship_the_same_php_files_and_keys(): void
    {
        $englishFiles = $this->phpFiles('en');

        foreach (self::LOCALES as $locale) {
            $this->assertSame($englishFiles, $this->phpFiles($locale), "{$locale}/ files differ from en/.");

            foreach ($englishFiles as $file) {
                $this->assertSame(
                    array_keys($this->phpTranslations('en', $file)),
                    array_keys($this->phpTranslations($locale, $file)),
                    "{$locale}/{$file} keys differ from en/{$file}.",
                );
            }
        }
    }

    public function test_php_translations_keep_the_placeholders_of_english(): void
    {
        if ($this->phpFiles('en') === []) {
            $this->markTestSkipped('The package ships no PHP translation files.');
        }

        foreach ($this->phpFiles('en') as $file) {
            $english = $this->phpTranslations('en', $file);

            foreach (self::LOCALES as $locale) {
                foreach ($this->phpTranslations($locale, $file) as $key => $value) {
                    $this->assertKeepsPlaceholders($english[$key] ?? '', $value, "{$locale}/{$file} \"{$key}\"");
                }
            }
        }
    }

    private function assertKeepsPlaceholders(string $source, string $translation, string $where): void
    {
        preg_match_all(self::PLACEHOLDER, $source, $placeholders);

        foreach ($placeholders[0] as $placeholder) {
            $this->assertStringContainsStringIgnoringCase(
                $placeholder,
                $translation,
                "{$where} is missing {$placeholder}.",
            );
        }
    }

    private function langPath(): string
    {
        return $this->packageRoot().'/resources/lang';
    }

    /** @return array<string, mixed> */
    private function jsonTranslations(string $locale): array
    {
        $path = $this->langPath()."/{$locale}.json";
        $json = file_get_contents($path);

        if ($json === false) {
            self::fail("Cannot read {$path}.");
        }

        $translations = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($translations);

        return $translations;
    }

    /** @return list<string> */
    private function phpFiles(string $locale): array
    {
        return array_map('basename', glob($this->langPath()."/{$locale}/*.php") ?: []);
    }

    /**
     * Nested groups flattened to dot keys, sorted, so files compare regardless of order.
     *
     * @return array<string, string>
     */
    private function phpTranslations(string $locale, string $file): array
    {
        $translations = require $this->langPath()."/{$locale}/{$file}";
        $this->assertIsArray($translations);

        $flat = $this->flatten($translations);
        ksort($flat);

        return $flat;
    }

    /**
     * @param  array<mixed>  $translations
     * @return array<string, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flat = [];

        foreach ($translations as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, "{$prefix}{$key}.");

                continue;
            }

            $this->assertIsString($value, "{$prefix}{$key} is not a string.");
            $flat["{$prefix}{$key}"] = $value;
        }

        return $flat;
    }
}
