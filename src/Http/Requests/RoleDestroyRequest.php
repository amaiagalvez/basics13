<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends TrashDestroyRequest<Role>
 */
final class RoleDestroyRequest extends TrashDestroyRequest
{
    /**
     * @return Role
     */
    protected function findTrashed(): Model
    {
        return Role::onlyTrashed()->findOrFail((int) $this->route('role'));
    }
}
