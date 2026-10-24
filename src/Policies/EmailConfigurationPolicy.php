<?php

namespace Basics13\Policies;

use Basics13\Models\EmailConfiguration;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Managed SMTP configuration abilities: `email.view` opens the list and `email.edit` covers every write,
 * the send test included.
 */
class EmailConfigurationPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'email.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can send a test email with the model.
     */
    public function sendTest(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can permanently delete the model. The default row is refused by
     * the model itself, whoever asks.
     */
    public function forceDelete(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, EmailConfiguration $emailConfiguration): bool
    {
        return $this->permits($user, 'email.edit');
    }
}
