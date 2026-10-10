<?php

namespace Basics13\Tests\Unit\Concerns;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Basics13\Tests\Fixtures\AuditActor;
use Illuminate\Database\QueryException;
use Basics13\Tests\Fixtures\AuditedRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Basics13\Tests\Fixtures\PlainAuditedRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TracksAuditColumnsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The audited records the trait writes its columns for: AuditedRecord keeps a deleted_at, while
     * PlainAuditedRecord has no soft deletes, so it is the one whose deleted_by is never written.
     *
     * @return array<string, array{class-string}>
     */
    public static function auditedRecords(): array
    {
        return [
            'audited record' => [AuditedRecord::class],
            'plain audited record' => [PlainAuditedRecord::class],
        ];
    }

    /**
     * The audited records that keep a deleted_at, the only ones with a deleted_by to write.
     *
     * @return array<string, array{class-string}>
     */
    public static function softDeletingRecords(): array
    {
        return [
            'audited record' => [AuditedRecord::class],
        ];
    }

    /**
     * @param  class-string<AuditedRecord|PlainAuditedRecord>  $record
     */
    #[DataProvider('auditedRecords')]
    public function test_creating_a_record_stamps_the_actor_on_created_by_and_updated_by(string $record): void
    {
        $actor = AuditActor::factory()->create();

        $this->actingAs($actor);
        $model = $record::factory()->create();

        $this->assertSame($actor->id, $model->created_by);
        $this->assertSame($actor->id, $model->updated_by);
        $this->assertNull($model->deleted_by);
    }

    /**
     * @param  class-string<AuditedRecord|PlainAuditedRecord>  $record
     */
    #[DataProvider('auditedRecords')]
    public function test_changing_a_record_stamps_the_new_actor_and_keeps_the_author(string $record): void
    {
        $author = AuditActor::factory()->create();
        $editor = AuditActor::factory()->create();

        $this->actingAs($author);
        $model = $record::factory()->create();

        $this->actingAs($editor);
        $model->update(['name' => 'Rewritten name']);

        $this->assertSame($author->id, $model->refresh()->created_by);
        $this->assertSame($editor->id, $model->updated_by);
    }

    /**
     * A write with nobody authenticated has no actor to record, so the trail of whoever really
     * created or changed the row has to survive it, as a console command or a queued job leaves it.
     *
     * @param  class-string<AuditedRecord|PlainAuditedRecord>  $record
     */
    #[DataProvider('auditedRecords')]
    public function test_a_write_without_an_authenticated_user_keeps_the_existing_trail(string $record): void
    {
        $author = AuditActor::factory()->create();

        $this->actingAs($author);
        $model = $record::factory()->create();

        $this->loggedOut();
        $model->update(['name' => 'Rewritten name']);

        $this->assertSame($author->id, $model->refresh()->created_by);
        $this->assertSame($author->id, $model->updated_by);
    }

    /**
     * @param  class-string<AuditedRecord>  $record
     */
    #[DataProvider('softDeletingRecords')]
    public function test_trashing_a_record_stamps_the_actor_on_deleted_by(string $record): void
    {
        $actor = AuditActor::factory()->create();

        $this->actingAs($actor);
        $model = $record::factory()->create();
        $model->delete();

        $this->assertSoftDeleted($model);
        $this->assertSame($actor->id, $model->refresh()->deleted_by);
    }

    /**
     * SoftDeletes::runSoftDelete() updates deleted_at and updated_at and nothing else, so a hook
     * that only set the attribute in memory would leave deleted_by empty on a trashed row.
     *
     * @param  class-string<AuditedRecord>  $record
     */
    #[DataProvider('softDeletingRecords')]
    public function test_trashing_without_an_authenticated_user_keeps_the_author_of_the_row(string $record): void
    {
        $author = AuditActor::factory()->create();

        $this->actingAs($author);
        $model = $record::factory()->create();

        $this->loggedOut();
        $model->delete();

        $this->assertSame($author->id, $model->refresh()->created_by);
        $this->assertNull($model->refresh()->deleted_by);
    }

    /**
     * @param  class-string<AuditedRecord>  $record
     */
    #[DataProvider('softDeletingRecords')]
    public function test_restoring_a_record_clears_deleted_by(string $record): void
    {
        $actor = AuditActor::factory()->create();

        $this->actingAs($actor);
        $model = $record::factory()->create();
        $model->delete();

        $this->actingAs($actor);
        $model->restore();

        $this->assertNotSoftDeleted($model->refresh());
        $this->assertNull($model->refresh()->deleted_by);
    }

    /**
     * A record with no soft deletes has no trashed state, so the trait writes no deleted_by for it
     * and the delete is the plain removal of the row.
     */
    public function test_a_record_without_soft_deletes_leaves_no_row_to_attribute_the_deletion_to(): void
    {
        $actor = AuditActor::factory()->create();

        $this->actingAs($actor);
        $model = PlainAuditedRecord::factory()->create();
        $id = $model->id;

        $model->delete();

        $this->assertDatabaseMissing('plain_audited_records', ['id' => $id]);
    }

    /**
     * A force delete leaves no row behind to attribute the deletion to, so the trait must not
     * write a deleted_by that nothing would ever read.
     */
    public function test_force_deleting_a_record_removes_it_without_leaving_a_deletion_trail(): void
    {
        $actor = AuditActor::factory()->create();

        $this->actingAs($actor);
        $model = AuditedRecord::factory()->create();
        $id = $model->id;

        $model->forceDelete();

        $this->assertDatabaseMissing('audited_records', ['id' => $id]);
    }

    /**
     * User deletion is restricted while an audit record still references that user.
     */
    public function test_deleting_the_actor_is_restricted_while_audit_records_reference_them(): void
    {
        $actor = AuditActor::factory()->create();

        $this->actingAs($actor);
        AuditedRecord::factory()->create();

        try {
            $actor->forceDelete();
            self::fail('A user with audit references must not be force deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $actor->id]);
        }
    }

    /**
     * The columns are written by the trait alone, so a payload cannot claim authorship.
     *
     * @param  class-string<AuditedRecord|PlainAuditedRecord>  $record
     */
    #[DataProvider('auditedRecords')]
    public function test_the_audit_columns_are_not_mass_assignable(string $record): void
    {
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            $this->assertFalse((new $record)->isFillable($column), "{$record} must not accept {$column}");
        }
    }

    /**
     * A write with nobody authenticated is the console and queue path: no user on the guard.
     */
    private function loggedOut(): void
    {
        Auth::forgetGuards();
    }
}
