<?php

namespace Basics13\Tests\Feature;

use PDOException;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Basics13\Http\Requests\RestoreRequest;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Controllers\TrashController;
use PHPUnit\Framework\Attributes\DataProvider;
use Basics13\Http\Requests\TrashDestroyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TrashControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('test/trash', fn () => null)->name('test.trash');
    }

    public function test_restore_trashed_restores_record_when_name_not_taken(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        $request = $this->restoreRequest($trashed);

        $response = $controller->restore($request);

        $this->assertSame(route('test.trash'), $response->getTargetUrl());
        $this->assertNotSoftDeleted($trashed->refresh());
        $this->assertSame(__('basics13::messages.restored'), session('status'));
    }

    public function test_restore_trashed_returns_conflict_when_name_taken(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        AuditedRecord::factory()->create(['name' => $trashed->name]);
        $request = $this->restoreRequest($trashed);

        $response = $controller->restore($request);

        $this->assertSame(route('test.trash'), $response->getTargetUrl());
        $this->assertSoftDeleted($trashed);
        $this->assertSame(__('basics13::messages.cannot_restore_name_taken'), session('error'));
    }

    public function test_restore_trashed_returns_missing_when_record_concurrently_deleted(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        $request = $this->restoreRequest($trashed);

        // Simulate concurrent force delete - the record is gone when we check
        $trashed->forceDelete();

        $response = $controller->restore($request);

        $this->assertSame(route('test.trash'), $response->getTargetUrl());
        $this->assertSame(__('basics13::messages.not_in_trash'), session('error'));
    }

    public function test_destroy_trashed_permanently_deletes_when_no_related_records(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        $request = $this->destroyRequest($trashed);

        $response = $controller->destroy($request);

        $this->assertSame(route('test.trash'), $response->getTargetUrl());
        $this->assertDatabaseMissing('audited_records', ['id' => $trashed->id]);
        $this->assertSame(__('basics13::messages.permanently_deleted'), session('status'));
    }

    public function test_destroy_trashed_returns_error_when_related_records_exist(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        $request = $this->destroyRequest($trashed);
        AuditedRecord::deleting(static fn (AuditedRecord $record): bool => ! $record->isForceDeleting());

        $response = $controller->destroy($request);

        $this->assertSame(route('test.trash'), $response->getTargetUrl());
        $this->assertSoftDeleted($trashed);
        $this->assertSame(__('basics13::messages.cannot_force_delete_related'), session('error'));
    }

    public function test_destroy_trashed_reports_a_real_foreign_key_restriction(): void
    {
        Schema::create('record_dependents', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_id')->constrained('audited_records')->restrictOnDelete();
        });

        try {
            $trashed = AuditedRecord::factory()->trashed()->create();
            DB::table('record_dependents')->insert(['record_id' => $trashed->id]);

            $response = (new TestTrashController)->destroy($this->destroyRequest($trashed));

            $this->assertSame(route('test.trash'), $response->getTargetUrl());
            $this->assertSame(__('basics13::messages.cannot_force_delete_related'), session('error'));
            $this->assertNull(session('status'));
            $this->assertSoftDeleted($trashed->refresh());
            $this->assertSame(1, DB::table('record_dependents')->count());
        } finally {
            Schema::dropIfExists('record_dependents');
        }
    }

    /** @param array{string, int|null, string} $errorInfo */
    #[DataProvider('deleteDatabaseErrors')]
    public function test_destroy_trashed_only_handles_foreign_key_restrictions(array $errorInfo, bool $restricted): void
    {
        $trashed = AuditedRecord::factory()->trashed()->create();
        $previous = new PDOException($errorInfo[2]);
        $previous->errorInfo = $errorInfo;
        $exception = new QueryException('sqlite', 'delete from audited_records', [], $previous);
        AuditedRecord::deleting(static function () use ($exception): never {
            throw $exception;
        });

        try {
            $response = (new TestTrashController)->destroy($this->destroyRequest($trashed));
            $this->assertTrue($restricted, 'Unrelated database errors must propagate.');
            $this->assertSame(route('test.trash'), $response->getTargetUrl());
            $this->assertSame(__('basics13::messages.cannot_force_delete_related'), session('error'));
        } catch (QueryException $caught) {
            $this->assertFalse($restricted);
            $this->assertSame($exception, $caught);
            $this->assertNull(session('error'));
        }

        $this->assertSoftDeleted($trashed->refresh());
        $this->assertNull(session('status'));
    }

    /** @return array<string, array{array{string, int|null, string}, bool}> */
    public static function deleteDatabaseErrors(): array
    {
        return [
            'MariaDB restrict' => [['23000', 1451, 'Cannot delete a parent row'], true],
            'PostgreSQL restrict' => [['23503', null, 'Foreign key violation'], true],
            'SQLite extended code' => [['23000', 787, 'FOREIGN KEY constraint failed'], true],
            'SQLite base code' => [['23000', 19, 'FOREIGN KEY constraint failed'], true],
            'SQLite unique constraint' => [['23000', 19, 'UNIQUE constraint failed'], false],
            'MariaDB insert constraint' => [['23000', 1452, 'Cannot add a child row'], false],
            'Database unavailable' => [['HY000', 1, 'Database unavailable'], false],
        ];
    }

    public function test_taken_by_returns_true_when_active_record_with_same_name_exists(): void
    {
        $controller = new TestTrashController;

        AuditedRecord::factory()->create(['name' => 'Existing Name']);

        $reflection = new \ReflectionMethod($controller, 'takenBy');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, AuditedRecord::query(), 'Existing Name');

        $this->assertTrue($result);
    }

    public function test_taken_by_returns_false_when_no_active_record_with_name_exists(): void
    {
        $controller = new TestTrashController;

        $reflection = new \ReflectionMethod($controller, 'takenBy');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, AuditedRecord::query(), 'Non Existent Name');

        $this->assertFalse($result);
    }

    public function test_restore_took_effect_returns_false_when_record_still_trashed(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();

        $reflection = new \ReflectionMethod($controller, 'restoreTookEffect');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, $trashed);

        $this->assertFalse($result);
    }

    public function test_restore_took_effect_returns_true_when_record_restored(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        $trashed->restore();

        $reflection = new \ReflectionMethod($controller, 'restoreTookEffect');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, $trashed);

        $this->assertTrue($result);
    }

    public function test_force_delete_locked_deletes_record(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = $this->destroyRequest($trashed);

        $reflection = new \ReflectionMethod($controller, 'forceDeleteLocked');
        $reflection->setAccessible(true);

        $deleted = $reflection->invoke($controller, $request);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('audited_records', ['id' => $trashed->id]);
    }

    public function test_force_delete_locked_returns_false_when_record_gone(): void
    {
        $controller = new TestTrashController;
        $trashed = AuditedRecord::factory()->trashed()->create();
        $trashed->forceDelete();

        $request = $this->destroyRequest($trashed);

        $reflection = new \ReflectionMethod($controller, 'forceDeleteLocked');
        $reflection->setAccessible(true);

        // When the record is force deleted, the lockForUpdate/firstOrFail throws ModelNotFoundException
        $this->expectException(ModelNotFoundException::class);

        $reflection->invoke($controller, $request);
    }

    /** @return RestoreRequest<AuditedRecord> */
    private function restoreRequest(AuditedRecord $record): RestoreRequest
    {
        return new /** @extends RestoreRequest<AuditedRecord> */ class($record) extends RestoreRequest
        {
            public function __construct(private readonly AuditedRecord $trashed)
            {
                parent::__construct();
            }

            protected function findTrashed(): AuditedRecord
            {
                return $this->trashed;
            }
        };
    }

    /** @return TrashDestroyRequest<AuditedRecord> */
    private function destroyRequest(AuditedRecord $record): TrashDestroyRequest
    {
        return new /** @extends TrashDestroyRequest<AuditedRecord> */ class($record) extends TrashDestroyRequest
        {
            public function __construct(private readonly AuditedRecord $trashed)
            {
                parent::__construct();
            }

            protected function findTrashed(): AuditedRecord
            {
                return $this->trashed;
            }
        };
    }
}

/** @extends TrashController<AuditedRecord> */
class TestTrashController extends TrashController
{
    /** @param RestoreRequest<AuditedRecord> $request */
    public function restore(RestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    /** @param TrashDestroyRequest<AuditedRecord> $request */
    public function destroy(TrashDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /** @param AuditedRecord $record */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /** @param AuditedRecord $record */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(AuditedRecord::query(), $record->name);
    }

    /** @return class-string<AuditedRecord> */
    protected function recordClass(): string
    {
        return AuditedRecord::class;
    }

    protected function trashRoute(): string
    {
        return 'test.trash';
    }
}
