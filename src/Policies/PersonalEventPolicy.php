<?php

namespace Basics13\Policies;

use Basics13\Models\PersonalEvent;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Personal event abilities. No permission is involved: every signed-in user keeps a calendar of
 * their own, and an event is only ever reachable by the user who owns it.
 */
class PersonalEventPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PersonalEvent $personalEvent): bool
    {
        return $this->owns($user, $personalEvent);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PersonalEvent $personalEvent): bool
    {
        return $this->owns($user, $personalEvent);
    }

    private function owns(User $user, PersonalEvent $personalEvent): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $personalEvent->user_id;
    }
}
