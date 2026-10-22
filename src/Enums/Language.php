<?php

namespace Basics13\Enums;

/**
 * The interface languages a user can pick. Each one is shown by its native name, never by a flag:
 * a language is not a country.
 */
enum Language: string
{
    case EU = 'eu';
    case ES = 'es';
    case FR = 'fr';
    case EN = 'en';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $language) {
            $options[$language->value] = $language->nativeName();
        }

        return $options;
    }

    public function nativeName(): string
    {
        return match ($this) {
            self::EU => 'Euskara',
            self::ES => 'Español',
            self::FR => 'Français',
            self::EN => 'English',
        };
    }
}
