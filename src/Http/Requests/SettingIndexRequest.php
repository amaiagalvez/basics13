<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Setting;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Basics13\Queries\Settings\SettingListQuery;

/**
 * The configuration screen shows one package at a time: the one the query string names, or the
 * first one. A package that seeds no variable does not exist here.
 */
class SettingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Setting::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'package' => ['nullable', 'string', 'max:'.MaxLength::string()],
        ];
    }

    public function package(SettingListQuery $query): string
    {
        $packages = $query->packages();
        $package = $this->string('package')->trim()->toString();

        if ($package === '') {
            return (string) $packages->first();
        }

        abort_unless($packages->contains($package), 404);

        return $package;
    }
}
