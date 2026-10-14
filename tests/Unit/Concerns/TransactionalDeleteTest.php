<?php

namespace Basics13\Tests\Unit\Concerns;

use RuntimeException;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Basics13\Tests\Fixtures\HardDeletingRecord;
use Basics13\Tests\Fixtures\SoftDeletingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionalDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The hooks that guard a delete take their locks in a transaction of their own, so that lock
     * only outlives them if the enclosing transaction is still open when they return.
     */
    public function test_the_deleting_hooks_run_inside_a_transaction_that_outlives_their_own(): void
    {
        $model = SoftDeletingRecord::factory()->create();
        $before = $this->transactionLevel();
        $levels = [];

        SoftDeletingRecord::deleting(function (SoftDeletingRecord $record) use (&$levels): bool {
            $levels[] = $this->transactionLevel();

            DB::transaction(static fn () => SoftDeletingRecord::withTrashed()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            $levels[] = $this->transactionLevel();

            return true;
        });

        $model->delete();

        $this->assertSame([$before + 1, $before + 1], $levels);
        $this->assertSame($before, $this->transactionLevel());
        $this->assertSoftDeleted($model);
    }

    /**
     * The write is what those locks are held for, so it has to land while the transaction is open
     * and not once the hooks have returned.
     */
    public function test_the_row_is_written_while_the_transaction_is_still_open(): void
    {
        $model = SoftDeletingRecord::factory()->create();
        $before = $this->transactionLevel();
        $writeLevel = null;

        DB::listen(function (QueryExecuted $query) use (&$writeLevel): void {
            if ($this->isSoftDeleteWrite($query)) {
                $writeLevel = $this->transactionLevel();
            }
        });

        $model->delete();

        $this->assertNotNull($writeLevel, 'The soft delete has to write the row.');
        $this->assertGreaterThan($before, $writeLevel);
    }

    /**
     * Without the transaction the row would stay trashed once a hook raised, leaving behind a
     * record the caller was told had not been deleted.
     */
    public function test_a_failure_after_the_write_leaves_the_row_untrashed(): void
    {
        $model = SoftDeletingRecord::factory()->create();
        $id = $model->getKey();
        $before = $this->transactionLevel();

        SoftDeletingRecord::deleted(static function (): never {
            throw new RuntimeException('The delete failed.');
        });

        try {
            $model->delete();
            self::fail('A hook that raises must abort the delete.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('soft_deleting_records', ['id' => $id, 'deleted_at' => null]);
        }

        $this->assertSame($before, $this->transactionLevel());
    }

    /**
     * A hook may refuse the delete, and then there is nothing to write: the transaction still has
     * to close, or the next caller would inherit it.
     */
    public function test_a_delete_refused_by_a_hook_leaves_the_row_and_closes_the_transaction(): void
    {
        $model = SoftDeletingRecord::factory()->create();
        $id = $model->getKey();
        $before = $this->transactionLevel();

        SoftDeletingRecord::deleting(static fn (): bool => false);

        $this->assertFalse($model->delete());
        $this->assertDatabaseHas('soft_deleting_records', ['id' => $id, 'deleted_at' => null]);
        $this->assertSame($before, $this->transactionLevel());
    }

    /**
     * A model with no soft deletes has no trashed state to write, so the trait opens no
     * transaction and the hooks run on the caller's own connection state.
     */
    public function test_a_record_without_soft_deletes_is_deleted_without_opening_a_transaction(): void
    {
        $model = HardDeletingRecord::factory()->create();
        $id = $model->getKey();
        $before = $this->transactionLevel();
        $levels = [];

        HardDeletingRecord::deleting(function () use (&$levels): bool {
            $levels[] = $this->transactionLevel();

            return true;
        });

        $model->delete();

        $this->assertSame([$before], $levels);
        $this->assertSame($before, $this->transactionLevel());
        $this->assertDatabaseMissing('hard_deleting_records', ['id' => $id]);
    }

    /**
     * A caller that opened a transaction owns the outcome, so the delete joins it instead of
     * committing a trash the caller still means to roll back.
     */
    public function test_a_delete_inside_a_caller_transaction_is_not_committed_early(): void
    {
        $model = SoftDeletingRecord::factory()->create();
        $id = $model->getKey();
        $before = $this->transactionLevel();

        DB::beginTransaction();

        try {
            $model->delete();
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseHas('soft_deleting_records', ['id' => $id, 'deleted_at' => null]);
        $this->assertSame($before, $this->transactionLevel());
    }

    /**
     * RefreshDatabase holds a transaction open for the test, so the level the trait works from is
     * one deeper than the one the caller started with.
     */
    private function transactionLevel(): int
    {
        return DB::connection()->transactionLevel();
    }

    /**
     * SoftDeletes writes the trash with a plain update, so it is the one statement in the delete
     * that has to run inside the transaction.
     */
    private function isSoftDeleteWrite(QueryExecuted $query): bool
    {
        $sql = strtolower($query->sql);

        return str_starts_with($sql, 'update') && str_contains($sql, 'soft_deleting_records');
    }
}
