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
            'filters' => ['nullable', 'array'],
            'filters.*.operator' => ['string'],
            'filters.*.value' => ['nullable'],
        ];
    }

    final public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /**
     * Get the advanced search filters from the request.
     *
     * @return array<string, array{operator: string, value: mixed}>|null
     */
    final public function filters(): ?array
    {
        $filters = $this->input('filters');

        if (! is_array($filters)) {
            return null;
        }

        $normalized = [];

        foreach ($filters as $key => $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $operator = $filter['operator'] ?? '';
            $value = $filter['value'] ?? null;

            if ($operator === '') {
                continue;
            }

            $normalized[$key] = [
                'operator' => $operator,
                'value' => $value,
            ];
        }

        return $normalized ?: null;
    }
}
