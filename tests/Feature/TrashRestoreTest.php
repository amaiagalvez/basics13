<?php

namespace Basics13\Tests\Feature;

use PDOException;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Basics13\Tests\Fixtures\AuditActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Basics13\Http\Requests\RestoreRequest;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Controllers\TrashController;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrashRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => str_repeat('a', 32)]);
        Gate::define('restore', static fn (AuditActor $actor, AuditedRecord $record): bool => true);
        Route::get('test/trash', static fn (): string => 'Trash')->name('test.trash');
        Route::bind('audit_record', static fn (string $id): AuditedRecord => AuditedRecord::onlyTrashed()->findOrFail($id));
    }

    public function test_restore_reports_a_duplicate_name_race_and_keeps_the_record_trashed(): void
    {
        $this->actingAs(AuditActor::factory()->create());
        $record = AuditedRecord::factory()->trashed()->create();
        $this->registerRestoreRoute($this->queryException(['23000', 19, 'UNIQUE constraint failed']));

        $this->post(route('test.restore', $record->id))
            ->assertRedirect(route('test.trash'))
            ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'))
            ->assertSessionMissing('status');

        $this->assertSoftDeleted($record->refresh());
    }

    public function test_restore_propagates_database_errors_that_are_not_duplicate_names(): void
    {
        $this->actingAs(AuditActor::factory()->create());
        $record = AuditedRecord::factory()->trashed()->create();
        $exception = $this->queryException(['HY000', 1, 'Database unavailable']);
        $this->registerRestoreRoute($exception);
        $this->withoutExceptionHandling();

        try {
            $this->post(route('test.restore', $record->id));
            $this->fail('A database error must not be reported as a name conflict.');
        } catch (QueryException $caught) {
            $this->assertSame($exception, $caught);
            $this->assertSoftDeleted($record->refresh());
            $this->assertNull(session('status'));
            $this->assertNull(session('error'));
        }
    }

    public function test_restore_with_conflict_resolution_uses_the_specific_success_message(): void
    {
        $this->actingAs(AuditActor::factory()->create());
        $record = AuditedRecord::factory()->trashed()->create();
        $this->registerRestoreRoute();

        $this->post(route('test.restore', $record->id), ['resolve_name_conflict' => true])
            ->assertRedirect(route('test.trash'))
            ->assertSessionHas('status', __('basics13::messages.restored_no_new_record'))
            ->assertSessionMissing('error');

        $this->assertNotSoftDeleted($record->refresh());
    }

    private function registerRestoreRoute(?QueryException $exception = null): void
    {
        $controller = new /** @extends TrashController<AuditedRecord> */ class($exception) extends TrashController
        {
            public function __construct(private readonly ?QueryException $exception) {}

            /** @param RestoreRequest<AuditedRecord> $request */
            public function restore(RestoreRequest $request): RedirectResponse
            {
                return $this->restoreTrashed($request);
            }

            /** @param AuditedRecord $record */
            protected function restoreTrashedRecord(Model $record): void
            {
                if ($this->exception !== null) {
                    throw $this->exception;
                }

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
        };

        Route::post('test/trash/{audit_record}/restore', static function (AuditRestoreRequest $request) use ($controller): RedirectResponse {
            return $controller->restore($request);
        })->middleware('web')->name('test.restore');
    }

    /** @param array{string, int, string} $errorInfo */
    private function queryException(array $errorInfo): QueryException
    {
        $previous = new PDOException($errorInfo[2]);
        $previous->errorInfo = $errorInfo;

        return new QueryException('sqlite', 'update audited_records set deleted_at = null', [], $previous);
    }
}

/** @extends RestoreRequest<AuditedRecord> */
class AuditRestoreRequest extends RestoreRequest
{
    protected function findTrashed(): AuditedRecord
    {
        /** @var AuditedRecord $record */
        $record = $this->route('audit_record');

        return $record;
    }
}
