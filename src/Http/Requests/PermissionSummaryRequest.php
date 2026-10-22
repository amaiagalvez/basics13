<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;

/**
 * The summary shows what each role gives each account, so seeing it is part of seeing the roles.
 */
class PermissionSummaryRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Role::class) ?? false;
    }
}
