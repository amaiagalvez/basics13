<?php

namespace Basics13\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Basics13\Models\EmailConfiguration;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class EmailConfigurationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'host', 'sender_email'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }

    public function authorize(): bool
    {
        $configuration = $this->route('email_configuration');

        return $configuration
            ? ($this->user()?->can('update', $configuration) ?? false)
            : ($this->user()?->can('create', EmailConfiguration::class) ?? false);
    }

    /**
     * `active` has no rule, so whatever a payload sends for it never reaches the model; `default`
     * is validated but written apart, through the transaction that clears it from the other rows.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:'.MaxLength::string(),
                Rule::unique(EmailConfiguration::class, 'name')->ignore($this->route('email_configuration'))->whereNull('deleted_at'),
            ],
            'sender_name' => ['required', 'string', 'max:'.MaxLength::string()],
            'sender_email' => ['required', 'email', 'max:'.MaxLength::string()],
            'host' => ['required', 'string', 'max:'.MaxLength::string()],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:'.MaxLength::string()],
            'password' => ['nullable', 'string', 'max:'.MaxLength::string()],
            'encryption' => ['required', Rule::in(EmailConfiguration::ENCRYPTIONS)],
            'default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The columns to write. The password is a one-way secret: the form never receives it, so a
     * blank field means "keep the stored one" and only a typed value replaces it.
     *
     * @return array<string, mixed>
     */
    public function configurationAttributes(): array
    {
        $attributes = Arr::except($this->validated(), ['default', 'reuse_deleted_name']);

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        return $attributes;
    }

    public function marksDefault(): bool
    {
        return (bool) $this->validated('default', false);
    }
}
