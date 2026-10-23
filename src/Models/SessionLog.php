<?php

namespace Basics13\Models;

use LogicException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Basics13\Enums\SessionEvent;
use Basics13\Enums\LoginFailureReason;
use Basics13\Policies\SessionLogPolicy;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Basics13\Database\Factories\SessionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * One immutable row of the access register: a successful login, a failed attempt or a lockout,
 * with who (when known), what was typed, from where and when. Rows are inserted by the login
 * audit and never change afterwards — the only write the application allows is stamping
 * `logged_out_at` once, and it never deletes. `login` and `user_agent` are truncated to their
 * column length, because Fortify does not bound what is typed and strict engines would fail.
 *
 * @property int $id
 * @property int|null $user_id
 * @property SessionEvent $event
 * @property LoginFailureReason|null $failure_reason
 * @property string $login
 * @property string $ip
 * @property string $user_agent
 * @property int|null $lockout_minutes
 * @property string|null $session_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $logged_out_at
 */
#[Fillable(['user_id', 'event', 'failure_reason', 'login', 'ip', 'user_agent', 'lockout_minutes', 'session_key', 'logged_out_at'])]
#[UsePolicy(SessionLogPolicy::class)]
#[UseFactory(SessionLogFactory::class)]
class SessionLog extends Model
{
    /** @use HasFactory<SessionLogFactory> */
    use HasFactory, ResolvesUserModel;

    public const UPDATED_AT = null;

    public const LOGIN_MAX_LENGTH = 255;

    public const USER_AGENT_MAX_LENGTH = 512;

    protected $table = 'BAS_sessions';

    /**
     * @return array{event: class-string<SessionEvent>, failure_reason: class-string<LoginFailureReason>, logged_out_at: string}
     */
    protected function casts(): array
    {
        return [
            'event' => SessionEvent::class,
            'failure_reason' => LoginFailureReason::class,
            'logged_out_at' => 'datetime',
        ];
    }

    /**
     * The register is immutable: an update only goes through when the single change is stamping
     * `logged_out_at` on a row that does not have it yet, and a delete never does. The logout
     * writes through the query builder on purpose (an atomic conditional update), so it does not
     * come through here.
     */
    protected static function booted(): void
    {
        static::updating(static function (self $log): bool {
            if (array_keys($log->getDirty()) === ['logged_out_at'] && $log->getRawOriginal('logged_out_at') === null) {
                return true;
            }

            throw new LogicException('Session log rows are immutable: only logged_out_at can be set, once.');
        });

        static::deleting(static function (): bool {
            throw new LogicException('Session log rows are never deleted.');
        });
    }

    /**
     * The account the attempt belongs to, trashed ones included: the register keeps pointing at
     * who it was even after the account is archived or in the trash.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @phpstan-ignore method.notFound (the configured users model soft-deletes) */
        return $this->belongsTo($this->userModelClass(), 'user_id')->withTrashed();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function login(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): string => Str::substr(is_string($value) ? $value : '', 0, self::LOGIN_MAX_LENGTH),
        );
    }

    /**
     * @return Attribute<string, string>
     */
    protected function userAgent(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): string => Str::substr(is_string($value) ? $value : '', 0, self::USER_AGENT_MAX_LENGTH),
        );
    }
}
