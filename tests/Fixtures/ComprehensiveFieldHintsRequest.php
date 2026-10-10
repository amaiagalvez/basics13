<?php

namespace Basics13\Tests\Fixtures;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request with various validation rules to test FieldHints comprehensively.
 */
final class ComprehensiveFieldHintsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Text field with min and max
            'name' => ['required', 'string', 'min:3', 'max:50'],

            // Text field with only min
            'code' => ['required', 'string', 'min:2'],

            // Text field with only max
            'nickname' => ['required', 'string', 'max:20'],

            // Text field with min, max, and unique
            'email' => ['required', 'string', 'email', 'min:5', 'max:100', Rule::unique('users')],

            // Field with after rule (date after another field)
            'start_date' => ['required', 'date', 'after:created_at'],

            // Field with after_or_equal rule
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

            // Field with required_with
            'optional_field' => ['nullable', 'string', 'required_with:other_field'],

            // Integer field (not text) with min/max
            'quantity' => ['integer', 'min:1', 'max:99'],

            // Field without required
            'notes' => ['nullable', 'string', 'max:500'],

            'unbounded_text' => ['nullable', 'string'],
            'custom_text' => [
                'required',
                'string',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === 'forbidden') {
                        $fail('This value is forbidden.');
                    }
                },
                'min:2',
            ],
        ];
    }
}
