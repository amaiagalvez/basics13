<?php

namespace Basics13\Http\Requests;

use Basics13\Models\UserType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RestoreRequest<UserType>
 */
final class UserTypeRestoreRequest extends RestoreRequest
{
    /**
     * @return UserType
     */
    protected function findTrashed(): Model
    {
        return UserType::onlyTrashed()
            ->findOrFail((int) $this->route('user_type'));
    }
}
