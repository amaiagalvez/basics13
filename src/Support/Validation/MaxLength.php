<?php

namespace Basics13\Support\Validation;

use LogicException;

/**
 * Typed access to the string length limits declared in config/basics13.php.
 *
 * The rules are written as strings, so they need the limit as text, while a Blade maxlength and a
 * boundary test need it as an int. Reading the config returns mixed, which neither accepts, so this
 * hands the value over already typed and fails loudly when the key is missing or not an integer.
 */
final class MaxLength
{
    /**
     * The ceiling for a field backed by a varchar column.
     */
    public static function string(): int
    {
        return self::fromConfig('string');
    }

    /**
     * The ceiling for a field backed by a longText column, which the database does not bound.
     */
    public static function longText(): int
    {
        return self::fromConfig('longtext');
    }

    private static function fromConfig(string $key): int
    {
        $limit = config('basics13.validation.max_length.'.$key);

        if (! is_int($limit)) {
            throw new LogicException("config/basics13.php must declare an integer validation.max_length.{$key}.");
        }

        return $limit;
    }
}
