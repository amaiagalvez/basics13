<?php

namespace Basics13\Http\Requests;

use Basics13\Concerns\ChecksPermissions;
use Basics13\Concerns\ResolvesUserModel;

class UserListRequest extends SearchableListRequest
{
    use ChecksPermissions;
    use ResolvesUserModel;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', $this->userModelClass()) ?? false;
    }

    /**
     * Whether the account form shows the role selector.
     */
    public function assignsRoles(): bool
    {
        $user = $this->user();

        return $user !== null && $this->permits($user, 'roles.manage');
    }

    /**
     * Whether the role selector offers system roles, which only the super-admin assigns.
     */
    public function assignsSystemRoles(): bool
    {
        $user = $this->user();

        return $user !== null && $this->isSuperAdmin($user);
    }
}
