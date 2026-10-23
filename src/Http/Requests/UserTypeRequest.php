<?php

namespace Basics13\Http\Requests;

use Basics13\Models\UserType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class UserTypeRequest extends FormRequest
{
    /**
     * Trim every name, and turn a blank translation into an absent one: a language with no name is
     * stored as NULL so the unique index of that column does not see an empty string as a value.
     */
    protected function prepareForValidation(): void
    {
        $names = [];

        foreach (UserType::NAME_FIELDS as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $names[$field] = $value === '' && $field !== 'name_eu' ? null : $value;
        }

        $this->merge($names);
    }

    public function authorize(): bool
    {
        $userType = $this->route('user_type');

        return $userType
            ? ($this->user()?->can('update', $userType) ?? false)
            : ($this->user()?->can('create', UserType::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name_eu' => [
                'required',
                'string',
                'min:2',
                'max:'.MaxLength::string(),
                $this->uniqueName('name_eu'),
            ],
            'name_es' => ['nullable', 'string', 'max:'.MaxLength::string(), $this->uniqueName('name_es')],
            'name_fr' => ['nullable', 'string', 'max:'.MaxLength::string(), $this->uniqueName('name_fr')],
            'name_en' => ['nullable', 'string', 'max:'.MaxLength::string(), $this->uniqueName('name_en')],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The name has to be free among the records that are not in the trash, in its own language only.
     * A name taken by a trashed record can be used again, which is what the create form offers to
     * restore instead.
     */
    private function uniqueName(string $column): Unique
    {
        return Rule::unique(UserType::class, $column)
            ->ignore($this->route('user_type'))
            ->whereNull('deleted_at');
    }
}
