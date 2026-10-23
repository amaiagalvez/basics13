<?php

namespace Basics13\Http\Requests;

use Basics13\Concerns\ChecksPermissions;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Concerns\DelegatesUserManagement;
use Illuminate\Contracts\Auth\Authenticatable;

class UserListRequest extends SearchableListRequest
{
    use ChecksPermissions;
    use DelegatesUserManagement;
    use ResolvesUserModel;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', $this->userModelClass()) ?? false;
    }

    /**
     * Whether the account form shows the role selector: for whoever manages roles, and for a type
     * admin, who is offered only the roles it could grant.
     */
    public function assignsRoles(): bool
    {
        $user = $this->user();

        return $user !== null && ($this->permits($user, 'roles.manage') || $this->managesAsTypeAdmin($user));
    }

    /**
     * The type admin the offered roles are narrowed to, or null when every role is offered.
     */
    public function roleGrantor(): ?Authenticatable
    {
        $user = $this->user();

        return $user !== null && ! $this->permits($user, 'roles.manage') ? $user : null;
    }

    /**
     * Whether the account form shows the type selector: for whoever edits accounts, and for a type
     * admin, who is offered only the types it administers.
     */
    public function assignsTypes(): bool
    {
        $user = $this->user();

        return $user !== null && ($this->permits($user, 'users.edit') || $this->managesAsTypeAdmin($user));
    }

    /**
     * The type admin the offered types are narrowed to, or null when every type is offered and the
     * admin flag can be set.
     */
    public function typeAdmin(): ?Authenticatable
    {
        $user = $this->user();

        return $user !== null && ! $this->permits($user, 'users.edit') ? $user : null;
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
