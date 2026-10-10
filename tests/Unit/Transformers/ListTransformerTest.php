<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Transformers\ListTransformer;

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

        $this->assertSame('No test records yet.', $transformer->buildEnvelope('active', '')['emptyMessage']);
        $this->assertSame('No test records match your search.', $transformer->buildEnvelope('active', 'search')['emptyMessage']);
    }

    public function test_empty_message_for_archived_state(): void
    {
        $transformer = new TestListTransformer;

        $this->assertSame(__('basics13::messages.no_archived_records'), $transformer->buildEnvelope('archived', '')['emptyMessage']);
        $this->assertSame('No test records match your search.', $transformer->buildEnvelope('archived', 'search')['emptyMessage']);
    }

    public function test_empty_message_for_trash_state(): void
    {
        $transformer = new TestListTransformer;

        $this->assertSame(__('basics13::messages.trash_is_empty'), $transformer->buildEnvelope('trash', '')['emptyMessage']);
        $this->assertSame('No test records match your search.', $transformer->buildEnvelope('trash', 'search')['emptyMessage']);
    }

    public function test_edit_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record)['edit'];

        $this->assertSame('form-modal', $result['type']);
        $this->assertSame(__('basics13::messages.edit'), $result['label']);
        $this->assertSame('pencil-square', $result['icon']);
        $this->assertSame(['test' => $record->id], $result['test']);
        $this->assertArrayHasKey('test', $result);
    }

    public function test_delete_action_returns_correct_structure(): void
    {
        $transformer = new TestListTransformer;
        $record = AuditedRecord::factory()->make(['id' => 1]);

        $result = $transformer->actions($record)['delete'];

        $this->assertSame('confirm-modal', $result['type']);
        $this->assertSame(__('basics13::messages.delete'), $result['label']);
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

        $this->assertCount(2, $result);
        $this->assertSame('Dashboard', $result[0]['label']);
        $this->assertSame('Test Resource', $result[1]['label']);
        $this->assertNull($result[1]['url']);
    }

    public function test_breadcrumbs_for_archived_state(): void
    {
        $transformer = new TestListTransformer;

        /** @var list<array{label: string, url: string|null}> $result */
        $result = $transformer->buildEnvelope('archived', '')['breadcrumbs'];

        $this->assertCount(3, $result);
        $this->assertSame('Dashboard', $result[0]['label']);
        $this->assertSame('Test Resource', $result[1]['label']);
        $this->assertSame(__('basics13::messages.archived_label'), $result[2]['label']);
    }
}

/**
 * @extends ListTransformer<AuditedRecord>
 */
class TestListTransformer extends ListTransformer
{
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

    protected function noMatchMessage(): string
    {
        return 'No test records match your search.';
    }

    protected function noRecordsMessage(): string
    {
        return 'No test records yet.';
    }

    protected function resourceKey(): string
    {
        return 'test';
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
}
