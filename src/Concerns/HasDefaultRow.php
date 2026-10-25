<?php

namespace Basics13\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the table carries the `default` flag, and only while it is active.
 *
 * - Marking a row clears the flag of every other one, trashed rows included, in one transaction.
 * - Archiving the default row clears its flag.
 * - Trashing keeps the flag in the column, so restoring the row brings it back and clears it from
 *   any other row. Whoever reads the default asks for an active row outside the trash, so a trashed
 *   default is no default at all until it is restored.
 * - The permanent delete of the default row is refused: another one has to be marked first.
 *
 * @phpstan-require-extends Model
 */
trait HasDefaultRow
{
    public static function bootHasDefaultRow(): void
    {
        static::saving(static function (self $model): void {
            if (! $model->getAttribute('active')) {
                $model->setAttribute('default', false);
            }
        });

        static::restored(static function (self $model): void {
            if ($model->getAttribute('default')) {
                $model->getConnection()->transaction(static fn () => $model->clearOtherDefaults());
            }
        });

        static::forceDeleting(static fn (self $model): bool => ! $model->getAttribute('default'));
    }

    /**
     * Make this row the default one. A default row is always active, so an archived one comes back.
     */
    public function markAsDefault(): void
    {
        $this->getConnection()->transaction(function (): void {
            $this->clearOtherDefaults();
            $this->forceFill(['default' => true, 'active' => true])->save();
        });
    }

    private function clearOtherDefaults(): void
    {
        // Lock every row, not only the current default: two marks running at once queue behind each
        // other even when no row carries the flag yet, so each one sees the other's default.
        static::withTrashed()->lockForUpdate()->get();

        static::withTrashed()
            ->whereKeyNot($this->getKey())
            ->where('default', true)
            ->update(['default' => false]);
    }
}
