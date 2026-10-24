<?php

namespace Basics13\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Basics13\Models\EmailTemplate;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class EmailTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function authorize(): bool
    {
        $template = $this->route('email_template');

        return $template
            ? ($this->user()?->can('update', $template) ?? false)
            : ($this->user()?->can('create', EmailTemplate::class) ?? false);
    }

    /**
     * English is required because every other language falls back to it. `active` has no rule;
     * `default` is written apart, through the transaction that clears it from the other rows.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:'.MaxLength::string(),
                Rule::unique(EmailTemplate::class, 'name')->ignore($this->route('email_template'))->whereNull('deleted_at'),
            ],
            'banner' => ['nullable', 'url', 'max:'.MaxLength::string()],
            'legal_text' => ['nullable', 'string', 'max:'.MaxLength::longText()],
        ];

        foreach (EmailTemplate::LOCALES as $locale) {
            $presence = $locale === EmailTemplate::FALLBACK_LOCALE ? 'required' : 'nullable';
            $rules['subject_'.$locale] = [$presence, 'string', 'max:'.MaxLength::string()];
            $rules['body_'.$locale] = [$presence, 'string', 'max:'.MaxLength::longText()];
        }

        return [
            ...$rules,
            'default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function templateAttributes(): array
    {
        return Arr::except($this->validated(), ['default', 'reuse_deleted_name']);
    }

    public function marksDefault(): bool
    {
        return (bool) $this->validated('default', false);
    }
}
