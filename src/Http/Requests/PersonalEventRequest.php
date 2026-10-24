<?php

namespace Basics13\Http\Requests;

use Basics13\Models\PersonalEvent;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class PersonalEventRequest extends FormRequest
{
    /**
     * Trim the title, and turn a blank end date or note into an absent one.
     */
    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['title', 'notes', 'end_date'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $values[$field] = $value === '' && $field !== 'title' ? null : $value;
            }
        }

        $this->merge($values);
    }

    public function authorize(): bool
    {
        $personalEvent = $this->route('personal_event');

        return $personalEvent
            ? ($this->user()?->can('update', $personalEvent) ?? false)
            : ($this->user()?->can('create', PersonalEvent::class) ?? false);
    }

    /**
     * The owner is not a field: it is always the signed-in user, so a `user_id` in the payload is
     * left out of the validated data.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.MaxLength::string()],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
