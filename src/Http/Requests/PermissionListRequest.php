<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The permission list takes no input. Seeing it is part of seeing the roles, which are what the
 * permissions are granted through.
 */
class PermissionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Role::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
