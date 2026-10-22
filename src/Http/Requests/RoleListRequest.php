<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;

class RoleListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Role::class) ?? false;
    }
}
