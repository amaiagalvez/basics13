<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Transformers\ListTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTransformerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('dashboard', fn () => null)->name('dashboard');
        Route::get('test', fn () => null)->name('test.active');
        Route::get('test/archived', fn () => null)->name('test.archived');
        Route::get('test/trash', fn () => null)->name('test.trash');
        Route::delete('test/{record}', fn () => null)->name('test.destroy');
        Route::patch('test/{record}/archive', fn () => null)->name('test.archive');
        Route::patch('test/archived/{record}', fn () => null)->name('test.activate');
        Route::patch('test/trash/{record}', fn () => null)->name('test.restore');
        Route::delete('test/trash/{record}', fn () => null)->name('test.trashDestroy');
    }

    public function test_search_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;

        $result = $transformer->buildSearch('test-action', 'test-value', 'test-placeholder');

        $this->assertSame([
            'action' => 'test-action',
            'value' => 'test-value',
            'placeholder' => 'test-placeholder',
        ], $result);
    }

    public function test_envelope_active_state(): void
    {
        $transformer = new TestListTransformer;

        /** @var array{resource: string, extraDateHeading: string, tabs: list<array{test: string, current: bool}>, create: bool} $result */
        $result = $transformer->buildEnvelope('active', 'search-term', ['active' => 5, 'archived' => 2, 'trashed' => 1]);

        $this->assertSame('Test Resource', $result['resource']);
        $this->assertSame(__('basics13::messages.created_at'), $result['extraDateHeading']);
        $this->assertSame('test-active-link', $result['tabs'][0]['test']);
        $this->assertTrue($result['tabs'][0]['current']);
        $this->assertFalse($result['tabs'][1]['current']);
        $this->assertFalse($result['tabs'][2]['current']);
        $this->assertTrue($result['create']);
    }

    public function test_envelope_archived_state(): void
    {
        $transformer = new TestListTransformer;

        /** @var array{extraDateHeading: string, tabs: list<array{current: bool}>, create: bool} $result */
        $result = $transformer->buildEnvelope('archived', '', ['active' => 5, 'archived' => 2, 'trashed' => 1]);

        $this->assertSame(__('basics13::messages.updated_at'), $result['extraDateHeading']);
        $this->assertFalse($result['tabs'][0]['current']);
        $this->assertTrue($result['tabs'][1]['current']);
        $this->assertFalse($result['tabs'][2]['current']);
        $this->assertFalse($result['create']);
    }

    public function test_envelope_trash_state(): void
    {
        $transformer = new TestListTransformer;

        /** @var array{extraDateHeading: string, tabs: list<array{current: bool}>, create: bool} $result */
        $result = $transformer->buildEnvelope('trash', 'search', ['active' => 5, 'archived' => 2, 'trashed' => 1]);

        $this->assertSame(__('basics13::messages.deleted_at'), $result['extraDateHeading']);
        $this->assertFalse($result['tabs'][0]['current']);
        $this->assertFalse($result['tabs'][1]['current']);
        $this->assertTrue($result['tabs'][2]['current']);
        $this->assertFalse($result['create']);
    }

    public function test_empty_message_for_active_state(): void
    {
        $transformer = new TestListTransformer;

        $this->assertSame('No records yet.', $transformer->buildEnvelope('active', '')['emptyMessage']);
        $this->assertSame(__('basics13::messages.no_matching_records'), $transformer->buildEnvelope('active', 'search')['emptyMessage']);
    }

    public function test_an_active_filter_narrows_the_list_like_a_search_does(): void
    {
        $this->assertFalse((new TestListTransformer)->buildEnvelope('archived', '')['narrowed']);

        request()->query->set('filters', ['name' => ['operator' => 'contains', 'value' => 'x']]);

        $envelope = (new TestListTransformer)->buildEnvelope('active', '');
        $this->assertTrue($envelope['narrowed']);
        $this->assertSame(__('basics13::messages.no_matching_records'), $envelope['emptyMessage']);
    }

    public function test_empty_message_for_archived_state(): void
    {
        $transformer = new TestListTransformer;

        $this->assertSame(__('basics13::messages.no_archived_records'), $transformer->buildEnvelope('archived', '')['emptyMessage']);
        $this->assertSame(__('basics13::messages.no_matching_records'), $transformer->buildEnvelope('archived', 'search')['emptyMessage']);
    }

    public function test_empty_message_for_trash_state(): void
    {
        $transformer = new TestListTransformer;

        $this->assertSame(__('basics13::messages.trash_is_empty'), $transformer->buildEnvelope('trash', '')['emptyMessage']);
        $this->assertSame(__('basics13::messages.no_matching_records'), $transformer->buildEnvelope('trash', 'search')['emptyMessage']);
    }

    public function test_default_routes_use_the_plural_resource_key_for_every_action(): void
    {
        $this->assertSame([
            'active' => 'tests.index',
            'archived' => 'tests.archived.index',
            'trash' => 'tests.trash.index',
            'destroy' => 'tests.destroy',
            'archive' => 'tests.archive',
            'activate' => 'tests.archived.activate',
            'restore' => 'tests.trash.restore',
            'trashDestroy' => 'tests.trash.destroy',
        ], (new TestListTransformer)->buildDefaultRoutes());
    }

    #[DataProvider('reservedResourceKeys')]
    public function test_edit_action_preserves_metadata_and_payload(string $resourceKey): void
    {
        $transformer = new TestListTransformer($resourceKey);
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record)['edit'];

        $this->assertSame('form-modal', $result['type']);
        $this->assertSame(__('basics13::messages.edit'), $result['label']);
        $this->assertSame('pencil-square', $result['icon']);
        $this->assertSame($resourceKey.'-edit-'.$record->id, $result['test']);
        $this->assertSame(['test' => $record->id], $result['payload']);
    }

    /** @return array<string, array{string}> */
    public static function reservedResourceKeys(): array
    {
        return [
            'test selector' => ['test'],
            'action type' => ['type'],
            'label' => ['label'],
            'icon' => ['icon'],
        ];
    }

    public function test_archive_hint_does_not_change_other_action_labels(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);
        $hint = 'Archive instead of deleting.';
        $action = $transformer->archiveWithHint($record, $hint);

        $this->assertSame($hint, $action['hint']);
        $this->assertSame(__('basics13::messages.archive'), $action['label']);
        $this->assertArrayNotHasKey('hint', $transformer->actions($record)['restore']);
    }

    public function test_delete_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1, 'name' => 'Alpha']);

        $result = $transformer->actions($record)['delete'];

        $this->assertSame('confirm-modal', $result['type']);
        $this->assertSame(__('basics13::messages.move_to_trash'), $result['label']);
        // The modal names the record it is about to move.
        $this->assertSame(__('basics13::messages.delete_record', ['name' => 'Alpha']), $result['confirmTitle']);
        $this->assertStringContainsString('Alpha', $result['confirmTitle']);
        $this->assertSame('trash', $result['icon']);
        $this->assertSame('test-delete-'.$record->id, $result['test']);
        $this->assertTrue($result['danger']);
        $this->assertSame(route('test.destroy', $record), $result['action']);
    }

    public function test_archive_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record)['archive'];

        $this->assertSame('confirm-modal', $result['type']);
        $this->assertSame(__('basics13::messages.archive'), $result['label']);
        $this->assertSame('archive-box', $result['icon']);
        $this->assertSame('test-archive-'.$record->id, $result['test']);
        $this->assertTrue($result['danger']);
    }

    public function test_activate_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record)['activate'];

        $this->assertSame('confirm-modal', $result['type']);
        $this->assertSame(__('basics13::messages.activate'), $result['label']);
        $this->assertSame('archive-box-arrow-down', $result['icon']);
        $this->assertSame('test-activate-'.$record->id, $result['test']);
        $this->assertFalse($result['danger']);
    }

    public function test_restore_action_returns_correct_structure_for_active(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record, true)['restore'];

        $this->assertSame('confirm-modal', $result['type']);
        $this->assertSame(__('basics13::messages.restore'), $result['label']);
        $this->assertSame('arrow-path', $result['icon']);
        $this->assertSame('test-restore-'.$record->id, $result['test']);
        $this->assertFalse($result['danger']);
        $this->assertSame(__('basics13::messages.record_returns_to_active'), $result['confirmText']);
    }

    public function test_restore_action_returns_correct_structure_for_archived(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record, false)['restore'];

        $this->assertSame(__('basics13::messages.record_returns_to_archived'), $result['confirmText']);
    }

    public function test_force_delete_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record)['forceDelete'];

        $this->assertSame('confirm-modal', $result['type']);
        $this->assertSame(__('basics13::messages.delete_permanently'), $result['label']);
        $this->assertSame('trash', $result['icon']);
        $this->assertSame('test-force-delete-'.$record->id, $result['test']);
        $this->assertTrue($result['danger']);
        $this->assertSame(__('basics13::messages.action_cannot_be_undone'), $result['confirmText']);
    }

    public function test_blocked_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;

        $result = $transformer->buildBlockedAction('Blocked Label', 'block-icon', 'test-blocked', 'Blocked hint');

        $this->assertSame('blocked', $result['type']);
        $this->assertSame('Blocked Label', $result['label']);
        $this->assertSame('block-icon', $result['icon']);
        $this->assertSame('test-blocked', $result['test']);
        $this->assertSame('Blocked hint', $result['hint']);
    }

    public function test_breadcrumbs_for_active_state(): void
    {
        $transformer = new TestListTransformer;

        /** @var list<array{label: string, url: string|null}> $result */
        $result = $transformer->buildEnvelope('active', '')['breadcrumbs'];

        $this->assertCount(1, $result);
        $this->assertSame('Test Resource', $result[0]['label']);
        $this->assertNull($result[0]['url']);
    }

    public function test_breadcrumbs_for_archived_state(): void
    {
        $transformer = new TestListTransformer;

        /** @var list<array{label: string, url: string|null}> $result */
        $result = $transformer->buildEnvelope('archived', '')['breadcrumbs'];

        $this->assertCount(2, $result);
        $this->assertSame('Test Resource', $result[0]['label']);
        $this->assertNotNull($result[0]['url']);
        $this->assertSame(__('basics13::messages.archived_label'), $result[1]['label']);
    }

    public function test_active_view_builds_the_envelope_and_the_editable_rows(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 7]);

        /** @var array{resource: string, create: bool, rows: list<array{test: int, extraDate: string|null, actions: list<array<string, mixed>>}>} $result */
        $result = $transformer->active($this->paginate($record), 'term', ['active' => 1, 'archived' => 0, 'trashed' => 0]);

        $this->assertSame('Test Resource', $result['resource']);
        $this->assertTrue($result['create']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(7, $result['rows'][0]['test']);
        $this->assertNull($result['rows'][0]['extraDate']);
        $this->assertSame(['form-modal', 'confirm-modal'], array_column($result['rows'][0]['actions'], 'type'));
        $this->assertSame('test-delete-7', $result['rows'][0]['actions'][1]['test']);
    }

    public function test_active_view_archives_the_record_when_deleting_is_blocked(): void
    {
        $transformer = new BlockingListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 3]);

        /** @var array{rows: list<array{actions: list<array<string, mixed>>}>} $result */
        $result = $transformer->active($this->paginate($record), '');

        $archive = $result['rows'][0]['actions'][1];

        $this->assertSame(__('basics13::messages.archive'), $archive['label']);
        $this->assertSame(__('basics13::messages.archive_instead_of_delete'), $archive['hint']);
        $this->assertSame('test-archive-3', $archive['test']);
    }

    public function test_archived_view_lists_records_with_the_activate_action(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 2]);

        /** @var array{create: bool, rows: list<array{actions: list<array<string, mixed>>}>} $result */
        $result = $transformer->archived($this->paginate($record), '');

        $this->assertFalse($result['create']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('confirm-modal', $result['rows'][0]['actions'][0]['type']);
        $this->assertSame('test-activate-2', $result['rows'][0]['actions'][0]['test']);
    }

    public function test_trash_view_lists_records_with_restore_and_force_delete(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 5]);

        /** @var array{create: bool, rows: list<array{actions: list<array<string, mixed>>}>} $result */
        $result = $transformer->trash($this->paginate($record), '');

        $this->assertFalse($result['create']);
        $this->assertSame(
            ['test-restore-5', 'test-force-delete-5'],
            array_column($result['rows'][0]['actions'], 'test'),
        );
    }

    public function test_trash_view_blocks_force_delete_when_the_condition_is_met(): void
    {
        $transformer = new BlockingListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 5]);

        /** @var array{rows: list<array{actions: list<array<string, mixed>>}>} $result */
        $result = $transformer->trash($this->paginate($record), '');

        $blocked = $result['rows'][0]['actions'][1];

        $this->assertSame('blocked', $blocked['type']);
        $this->assertSame('lock-closed', $blocked['icon']);
        $this->assertSame('test-force-delete-blocked-5', $blocked['test']);
        $this->assertSame('The record is referenced by other data.', $blocked['hint']);
    }

    /**
     * @return LengthAwarePaginator<int, AuditedRecord>
     */
    private function paginate(AuditedRecord $record): LengthAwarePaginator
    {
        return new LengthAwarePaginator([$record], 1, 15);
    }
}

/**
 * @extends ListTransformer<AuditedRecord>
 */
class TestListTransformer extends ListTransformer
{
    public function __construct(private readonly string $resource = 'test') {}

    /** @return array<string, mixed> */
    public function archiveWithHint(AuditedRecord $record, string $hint): array
    {
        return $this->archiveAction($record, $hint);
    }

    /**
     * @return array{action: string, value: string, placeholder: string}
     */
    public function buildSearch(string $action, string $value, string $placeholder): array
    {
        return $this->search($action, $value, $placeholder);
    }

    /**
     * @param  'active'|'archived'|'trash'  $state
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function buildEnvelope(string $state, string $search, ?array $counts = null): array
    {
        return $this->envelope($state, $search, $counts);
    }

    /**
     * @return array{active: string, archived: string, trash: string, destroy: string, archive: string, activate: string, restore: string, trashDestroy: string}
     */
    public function buildDefaultRoutes(): array
    {
        return parent::routes();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function actions(AuditedRecord $record, bool $active = true): array
    {
        return [
            'edit' => $this->editAction($record),
            'delete' => $this->deleteAction($record),
            'archive' => $this->archiveAction($record),
            'activate' => $this->activateAction($record),
            'restore' => $this->restoreAction($record, $active),
            'forceDelete' => $this->forceDeleteAction($record),
        ];
    }

    /**
     * @return array{type: string, label: string, icon: string, test: string, hint: string}
     */
    public function buildBlockedAction(string $label, string $icon, string $test, string $hint): array
    {
        return $this->blockedAction($label, $icon, $test, $hint);
    }

    protected function resourceLabel(): string
    {
        return 'Test Resource';
    }

    protected function resourceKey(): string
    {
        return $this->resource;
    }

    /**
     * @return array{active: string, archived: string, trash: string, destroy: string, archive: string, activate: string, restore: string, trashDestroy: string}
     */
    protected function routes(): array
    {
        return [
            'active' => 'test.active',
            'archived' => 'test.archived',
            'trash' => 'test.trash',
            'destroy' => 'test.destroy',
            'archive' => 'test.archive',
            'activate' => 'test.activate',
            'restore' => 'test.restore',
            'trashDestroy' => 'test.trashDestroy',
        ];
    }

    /**
     * @param  AuditedRecord  $record
     * @return array{test: int}
     */
    protected function editPayload(Model $record): array
    {
        return ['test' => $record->id];
    }

    protected function columns(Model $record, array $extra): array
    {
        return ['test' => $record->id];
    }

    protected function getDeleteBlockCondition(Model $record): bool
    {
        return false;
    }

    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return false;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return '';
    }

    protected function getExtraDate(Model $record, string $scope): ?string
    {
        return null;
    }

    protected function getRestoreActive(Model $record): bool
    {
        return true;
    }
}

/**
 * Blocks both destructive actions so the archive-instead-of-delete and the blocked force-delete
 * branches are reached through the public list views.
 */
final class BlockingListTransformer extends TestListTransformer
{
    protected function getDeleteBlockCondition(Model $record): bool
    {
        return true;
    }

    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return true;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return 'The record is referenced by other data.';
    }
}
