<?php

namespace Basics13\Http\Requests;

use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared rules and normalisation for the remote select-options endpoints. Only `authorize()`
 * depends on the resource, so a concrete request names its model and nothing else.
 */
abstract class SearchableSelectOptionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    final public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.MaxLength::string()],
            'context' => ['nullable', 'string', 'max:'.MaxLength::string()],
        ];
    }

    /**
     * Where the selector is being used, for an integration that offers different options there,
     * e.g. `edit` while a record is being edited. Empty when the caller says nothing.
     */
    final public function context(): string
    {
        return $this->string('context')->trim()->toString();
    }

    final public function search(): string
    {
        return $this->string('q')->trim()->toString();
    }
}
