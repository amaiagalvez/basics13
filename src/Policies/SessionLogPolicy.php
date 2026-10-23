<?php

namespace Basics13\Policies;

use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Session log abilities. The register is read-only: the only ability is listing it, granted by
 * `sessions.view`. There is no create, update or delete: rows are written by the login audit and
 * never change afterwards, so no route asks for those abilities.
 */
class SessionLogPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can list the session log.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'sessions.view');
    }
}
