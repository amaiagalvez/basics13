<?php

namespace Basics13\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @return array<string, array<int, mixed>>
 */
final class BoundedLengthRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['string', 'min:3', 'max:12'],
            'code' => ['string', 'min:2'],
            'nickname' => ['string', 'max:8'],
            'title' => ['string', 'min:5', 'max:20', Rule::unique('titles')],
            'quantity' => ['integer', 'min:1', 'max:99'],
        ];
    }
}
