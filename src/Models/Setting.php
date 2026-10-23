<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Basics13\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;
use Basics13\Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * One runtime configuration variable seeded by the package that reads it. Only `value` changes
 * from the interface; the name and description are translated labels. Read with `Setting::get()`:
 * one indexed query, no cache, so a changed value applies on the next request.
 *
 * @property int $id
 * @property string $package
 * @property string $class
 * @property string $variable
 * @property SettingType $type
 * @property string|null $value
 * @property string $name_eu
 * @property string $name_es
 * @property string $name_fr
 * @property string $name_en
 * @property string $description_eu
 * @property string $description_es
 * @property string $description_fr
 * @property string $description_en
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 */
#[Fillable(['package', 'class', 'variable', 'type', 'value', 'name_eu', 'name_es', 'name_fr', 'name_en', 'description_eu', 'description_es', 'description_fr', 'description_en'])]
#[UseFactory(SettingFactory::class)]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * The bounds an integer variable accepts in the configuration screen: minutes, from one to a day.
     */
    public const MIN_INTEGER = 1;

    public const MAX_INTEGER = 1440;

    protected $table = 'BAS_settings';

    /**
     * @return array{type: class-string<SettingType>}
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    /**
     * The current value of a variable, cast to its type. The default applies when the row is
     * missing, the stored value is empty or an integer is not a whole number within range: the
     * reader always gets a usable value.
     */
    public static function get(string $package, string $variable, int|string $default): int|string
    {
        $setting = static::query()
            ->where('package', $package)
            ->where('variable', $variable)
            ->first();

        if (! $setting instanceof self) {
            return $default;
        }

        $value = $setting->value;

        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        if ($setting->type !== SettingType::Integer) {
            return $value;
        }

        $integer = filter_var(trim($value), FILTER_VALIDATE_INT);

        if ($integer === false || $integer < self::MIN_INTEGER || $integer > self::MAX_INTEGER) {
            return $default;
        }

        return $integer;
    }

    /**
     * The translated name of the variable, in the interface language or English when that
     * translation is empty.
     */
    public function label(): string
    {
        return $this->translated('name');
    }

    /**
     * The translated description of the variable, with the same fallback as the label.
     */
    public function description(): string
    {
        return $this->translated('description');
    }

    private function translated(string $column): string
    {
        $value = $this->getAttribute($column.'_'.app()->getLocale());

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $english = $this->getAttribute($column.'_en');

        return is_string($english) ? $english : '';
    }
}
