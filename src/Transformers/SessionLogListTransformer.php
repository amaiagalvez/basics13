<?php

namespace Basics13\Transformers;

use Basics13\Models\SessionLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Shapes a register row for the session list. It has no actions: the register is read-only.
 */
class SessionLogListTransformer
{
    /**
     * @return array{id: int, createdAt: string|null, event: string, eventLabel: string, reason: string|null, user: string|null, userTrashed: bool, impersonator: string|null, login: string, ip: string, userAgent: string, lockoutMinutes: int|null, loggedOutAt: string|null}
     */
    public function row(SessionLog $session): array
    {
        $user = $session->relationLoaded('user') ? $session->getRelation('user') : null;
        $impersonator = $session->relationLoaded('impersonator') ? $session->getRelation('impersonator') : null;

        return [
            'id' => $session->id,
            'createdAt' => $session->created_at?->toIso8601String(),
            'event' => $session->event->value,
            'eventLabel' => $session->event->label(),
            'reason' => $session->failure_reason?->label(),
            'user' => $user instanceof Model ? (string) $user->getAttribute('name') : null,
            'userTrashed' => $user instanceof Model && $user->getAttribute('deleted_at') !== null,
            'impersonator' => $impersonator instanceof Model ? (string) $impersonator->getAttribute('name') : null,
            'login' => $session->login,
            'ip' => $session->ip,
            'userAgent' => $session->user_agent,
            'lockoutMinutes' => $session->lockout_minutes,
            'loggedOutAt' => $session->logged_out_at?->toIso8601String(),
        ];
    }
}
