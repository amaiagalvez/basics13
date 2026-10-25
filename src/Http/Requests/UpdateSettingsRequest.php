<?php

namespace Basics13\Http\Requests;

use Basics13\Enums\Language;
use Basics13\Models\Setting;
use Basics13\Enums\SettingType;
use Illuminate\Support\Facades\Route;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Basics13\Queries\Settings\SettingListQuery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Saves the values of one package. Only `values.{variable}` is read: the type, the class and the
 * translated names come from the seeding migration and no request changes them. The rules follow
 * each variable's type.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * @var EloquentCollection<int, Setting>|null
     */
    private ?EloquentCollection $settings = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $settings = $this->settings();
        abort_if($settings->isEmpty(), 404);

        return $settings->every(static fn (Setting $setting): bool => $user->can('update', $setting));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = ['values' => ['required', 'array']];

        foreach ($this->settings() as $setting) {
            $rules['values.'.$setting->variable] = match ($setting->type) {
                SettingType::Integer => ['required', 'integer', 'between:'.Setting::MIN_INTEGER.','.Setting::MAX_INTEGER],
                SettingType::Email => ['nullable', 'email', 'max:'.MaxLength::string()],
                SettingType::String => ['nullable', 'string', 'max:'.MaxLength::string()],
                SettingType::Languages => ['required', 'array', 'min:1'],
            };

            if ($setting->type === SettingType::Languages) {
                $rules['values.'.$setting->variable.'.*'] = ['string', 'in:'.implode(',', Language::codes())];
            }
        }

        return $rules;
    }

    /**
     * Each value is named by its translated label in the error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->settings()
            ->mapWithKeys(static fn (Setting $setting): array => ['values.'.$setting->variable => $setting->label()])
            ->all();
    }

    /**
     * The variables of the package in the route.
     *
     * @return EloquentCollection<int, Setting>
     */
    public function settings(): EloquentCollection
    {
        $package = Route::current()?->parameter('package');

        return $this->settings ??= app(SettingListQuery::class)->forPackage(is_string($package) ? $package : '');
    }

    /**
     * The validated value of a variable, as the column stores it: empty for a blank one, and the
     * chosen codes comma-separated, in enum order, for a list of languages.
     */
    public function valueFor(Setting $setting): string
    {
        $value = $this->validated('values.'.$setting->variable);

        if (is_array($value)) {
            return implode(',', array_intersect(Language::codes(), $value));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
