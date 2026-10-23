<?php

namespace Basics13\Queries\Sessions;

use Illuminate\Support\Carbon;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The access register, newest first, searchable by the typed login and filtered by date, account
 * and result. The date filter compares the column against day bounds instead of `whereDate`, so the
 * `created_at` indexes keep serving it.
 *
 * @extends ListQueryBase<SessionLog>
 *
 * @phpstan-import-type SearchFields from ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class SessionLogListQuery extends ListQueryBase
{
    use ResolvesUserModel;

    /**
     * @var non-empty-list<literal-string>
     */
    private const SEARCH_COLUMNS = ['BAS_sessions.login'];

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, SessionLog>
     */
    public function page(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            SessionLog::query()
                ->with(['user', 'impersonator'])
                ->orderByDesc('BAS_sessions.created_at')
                ->orderByDesc('BAS_sessions.id'),
            $search,
            self::SEARCH_COLUMNS,
            $filters,
        );
    }

    /**
     * @return SearchFields
     */
    public function searchableFields(string $scope): array
    {
        return [
            'created_at' => [
                'column' => 'BAS_sessions.created_at',
                'label' => 'Date',
                'type' => 'date',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
            'event' => [
                'column' => 'BAS_sessions.event',
                'label' => 'Result',
                'type' => 'select',
                'visible' => true,
                'order' => 20,
                'group' => 'basic',
                'options' => static fn (string $scope): array => collect(SessionEvent::cases())
                    ->mapWithKeys(static fn (SessionEvent $event): array => [$event->value => $event->label()])
                    ->all(),
                'multiple' => true,
            ],
            'login' => [
                'column' => 'BAS_sessions.login',
                'label' => 'Typed login',
                'type' => 'text',
                'visible' => true,
                'order' => 30,
                'group' => 'basic',
            ],
            // Every account, archived, trashed and the super-admin included: the register keeps
            // their attempts too.
            'user_id' => [
                'column' => 'BAS_sessions.user_id',
                'label' => 'User',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'options' => fn (string $scope): array => $this->accountOptions(),
                'multiple' => true,
            ],
        ];
    }

    /**
     * Every account by name, archived, trashed and the super-admin included.
     *
     * @return array<int|string, string>
     */
    private function accountOptions(): array
    {
        /** @phpstan-ignore method.notFound (the configured users model soft-deletes) */
        $accounts = $this->userModelClass()::query()->withTrashed();

        return $accounts
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Day bounds on the column, both ends included: `between` a and b keeps every row from the
     * start of a to the end of b.
     *
     * @param  Builder<SessionLog>  $query
     */
    protected function applyDateFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        $day = static fn (mixed $date): ?Carbon => is_string($date) && $date !== ''
            ? rescue(static fn (): Carbon => Carbon::parse($date)->startOfDay(), report: false)
            : null;

        $single = $day($value);

        match ($operator) {
            'equals' => $single ? $query->where($column, '>=', $single)->where($column, '<', $single->copy()->addDay()) : null,
            'before' => $single ? $query->where($column, '<', $single) : null,
            'after' => $single ? $query->where($column, '>=', $single->copy()->addDay()) : null,
            'between' => is_array($value) && array_is_list($value) && count($value) === 2
                && ($from = $day($value[0])) && ($to = $day($value[1]))
                ? $query->where($column, '>=', $from)->where($column, '<', $to->copy()->addDay()) : null,
            default => null,
        };
    }

    /**
     * @return class-string<Model>
     */
    protected function getModelClass(): string
    {
        return SessionLog::class;
    }

    /**
     * The register has no integration, so the base hook gets a no-op that appends nothing.
     */
    protected function integration(): object
    {
        return new class
        {
            /**
             * @param  non-empty-list<literal-string>  $columns
             * @return non-empty-list<literal-string>
             */
            public function searchColumns(string $scope, array $columns): array
            {
                return $columns;
            }
        };
    }
}
