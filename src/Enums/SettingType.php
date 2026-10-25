<?php

namespace Basics13\Enums;

/**
 * The kind of value a `BAS_settings` variable holds. It drives how `Setting::get()` casts the
 * stored text and which rule `UpdateSettingsRequest` applies to the form field.
 */
enum SettingType: string
{
    case Integer = 'integer';
    case String = 'string';
    case Email = 'email';
    case Languages = 'languages';
}
