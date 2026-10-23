<?php

namespace Basics13\Queries\Sessions;

use Carbon\CarbonImmutable;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the administration dashboard says about accounts and access over the last days, today
 * included. Accounts are the ones not trashed. Active means a successful sign-in in the period
 * (the register is the only usage signal); inactive, an account that is not archived and has none;
 * pending, an unverified email. Growth compares the new accounts with those created before the
 * period: there is no history of deletions, so trashed accounts are left out of both sides.
 * Failures include the attempts rejected because the login was locked; the lockout rows are a
 * consequence of a failure, not one more. The flow figures (active, new, failed) also come for the
 * window of the same length right before, so the view can show the change. Days are grouped with
 * `DATE(created_at)`, which matches the application's days because `app.timezone` is UTC.
 *
 * Alerts come only when their condition holds. Inactivity looks at a fixed window, not the period.
 * "Suspended" means archived with `updated_at` in the period: there is no archive date, so another
 * edit of an already archived account counts too.
 */
final class SessionDashboardQuery
{
    use ResolvesUserModel;

    /**
     * Days without a successful sign-in that make an account inactive for the alert.
     */
    public const INACTIVITY_DAYS = 30;

    /**
     * A surge of new accounts needs at least this many in the period...
     */
    public const SURGE_MIN_NEW = 5;

    /**
     * ...and this many times those of the previous period.
     */
    public const SURGE_FACTOR = 2;

    /**
     * @return array{
     *     users: int,
     *     active: array{current: int, previous: int},
     *     new: array{current: int, previous: int},
     *     inactive: int,
     *     pending: int,
     *     growth: float|null,
     *     failed: array{current: int, previous: int},
     *     days: list<string>,
     *     signIns: list<int>,
     *     uniqueUsers: list<int>,
     *     alerts: array{pending: int, inactive: int, surge: bool, suspended: int}
     * }
     */
    public function summary(int $days): array
    {
        $to = CarbonImmutable::tomorrow();
        $from = $to->subDays($days);
        $previousFrom = $from->subDays($days);

        $active = $this->activeUserIds($from, $to);
        $new = $this->accounts()->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        $before = $this->accounts()->where('created_at', '<', $from)->count();
        $previousNew = $this->accounts()->where('created_at', '>=', $previousFrom)->where('created_at', '<', $from)->count();
        $pending = $this->accounts()->whereNull('email_verified_at')->count();

        return [
            'users' => $this->accounts()->count(),
            'active' => ['current' => $active->count(), 'previous' => $this->activeUserIds($previousFrom, $from)->count()],
            'new' => ['current' => $new, 'previous' => $previousNew],
            'inactive' => $this->accounts()->where('active', true)->whereKeyNot($active->all())->count(),
            'pending' => $pending,
            'growth' => $before > 0 ? round($new / $before * 100, 1) : null,
            'failed' => ['current' => $this->failures($from, $to), 'previous' => $this->failures($previousFrom, $from)],
            ...$this->daily($from, $days),
            'alerts' => [
                'pending' => $pending,
                'inactive' => $this->accounts()->where('active', true)
                    ->whereKeyNot($this->activeUserIds($to->subDays(self::INACTIVITY_DAYS), $to)->all())
                    ->count(),
                'surge' => $new >= self::SURGE_MIN_NEW && $new >= $previousNew * self::SURGE_FACTOR,
                'suspended' => $this->accounts()->where('active', false)
                    ->where('updated_at', '>=', $from)->where('updated_at', '<', $to)
                    ->count(),
            ],
        ];
    }

    /**
     * @return Builder<Model>
     */
    private function accounts(): Builder
    {
        return $this->userModelClass()::query();
    }

    /**
     * @return Collection<int, int>
     */
    private function activeUserIds(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return SessionLog::query()
            ->where('event', SessionEvent::Login)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id);
    }

    private function failures(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return SessionLog::query()
            ->where('event', SessionEvent::Failed)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }

    /**
     * @return array{days: list<string>, signIns: list<int>, uniqueUsers: list<int>}
     */
    private function daily(CarbonImmutable $from, int $days): array
    {
        /** @var array<string, object{total: int|string, unique_users: int|string}> $perDay */
        $perDay = SessionLog::query()
            ->toBase()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, COUNT(DISTINCT user_id) as unique_users')
            ->where('event', SessionEvent::Login->value)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $from->addDays($days))
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->day)
            ->all();

        $dayKeys = array_map(static fn (int $offset): string => $from->addDays($offset)->toDateString(), range(0, $days - 1));

        return [
            'days' => $dayKeys,
            'signIns' => array_map(static fn (string $day): int => (int) ($perDay[$day]->total ?? 0), $dayKeys),
            'uniqueUsers' => array_map(static fn (string $day): int => (int) ($perDay[$day]->unique_users ?? 0), $dayKeys),
        ];
    }
}
