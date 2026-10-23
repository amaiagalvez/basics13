<?php

namespace Basics13\Policies;

use Basics13\Models\Setting;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Application configuration abilities: `settings.view` opens the screen and `settings.edit`
 * changes a value. Everything else about a variable (its type, names and descriptions) comes from
 * the seeding migration and never changes from a request.
 */
class SettingPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can list the configuration variables.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'settings.view');
    }

    /**
     * Determine whether the user can change the value of a variable.
     */
    public function update(User $user, Setting $setting): bool
    {
        return $this->permits($user, 'settings.edit');
    }
}
