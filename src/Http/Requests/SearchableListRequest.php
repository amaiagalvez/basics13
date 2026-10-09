<?php

namespace Basics13\Http\Requests;

use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

abstract class SearchableListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    final public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.MaxLength::string()],
        ];
    }

    final public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }
}