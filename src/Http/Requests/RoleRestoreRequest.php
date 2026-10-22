<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RestoreRequest<Role>
 */
final class RoleRestoreRequest extends RestoreRequest
{
    /**
     * @return Role
     */
    protected function findTrashed(): Model
    {
        return Role::onlyTrashed()->findOrFail((int) $this->route('role'));
    }
}
