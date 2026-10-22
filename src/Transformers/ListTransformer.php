<?php

namespace Basics13\Transformers;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the list views of every resource.
 *
 * Entities, projects and epics share the same page skeleton: the state tabs, breadcrumbs, empty
 * and search messages and the six row actions. This base owns that shared behavior; a transformer
 * describes its resource label, columns and edit payload.
 *
 * @template TRecord of Model
 */
abstract class ListTransformer
{
    /**
     * @return array{action: string, value: string, placeholder: string}
     */
    final protected function search(string $action, string $value, string $placeholder): array
    {
        return [
            'action' => $action,
            'value' => $value,
            'placeholder' => $placeholder,
        ];
    }

    /**
     * Everything a list view needs above its rows.
     *
     * @param  'active'|'archived'|'trash'  $state
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    final protected function envelope(string $state, string $search, ?array $counts): array
    {
        return [
            'resource' => $this->resourceLabel(),
            'breadcrumbs' => $this->breadcrumbs($state),
            'extraDateHeading' => match ($state) {
                'active' => __('basics13::messages.created_at'),
                'archived' => __('basics13::messages.updated_at'),
                'trash' => __('basics13::messages.deleted_at'),
            },
            'emptyMessage' => $this->emptyMessage($state, $this->narrowed($search)),
            'narrowed' => $this->narrowed($search),
            'search' => $this->search(
                route($this->routes()[$state]),
                $search,
                $this->searchPlaceholder(),
            ),
            'tabs' => $this->tabs($state, $counts),
            'create' => $state === 'active',
        ];
    }

    /**
     * State tabs shown under the page heading: the resource itself plus its other states, each one
     * with the number of records it holds.
     *
     * @param  'active'|'archived'|'trash'  $state
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return list<array{label: string, url: string, current: bool, count: int|null, test: string}>
     */
    final protected function tabs(string $state, ?array $counts): array
    {
        return [
            $this->tab($this->resourceLabel(), route($this->routes()['active']), 'active', 'active', $state, $counts),
            $this->tab(__('basics13::messages.archived_label'), route($this->routes()['archived']), 'archived', 'archived', $state, $counts),
            $this->tab(__('basics13::messages.trash_label'), route($this->routes()['trash']), 'trash', 'trashed', $state, $counts),
        ];
    }

    /**
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array{label: string, url: string, current: bool, count: int|null, test: string}
     */
    private function tab(
        string $label,
        string $url,
        string $tab,
        string $count,
        string $state,
        ?array $counts,
    ): array {
        return [
            'label' => $label,
            'url' => $url,
            'current' => $state === $tab,
            'count' => $counts[$count] ?? null,
            'test' => $this->resourceKey().'-'.$tab.'-link',
        ];
    }

    /**
     * @param  'active'|'archived'|'trash'  $state
     * @return list<array{label: string, url: string|null}>
     */
    private function breadcrumbs(string $state): array
    {
        // Root lists start at the resource: the dashboard is one sidebar click away, not a parent.
        $resource = ['label' => $this->resourceLabel(), 'url' => route($this->routes()['active'])];

        return match ($state) {
            'active' => [['label' => $this->resourceLabel(), 'url' => null]],
            'archived' => [$resource, ['label' => __('basics13::messages.archived_label'), 'url' => null]],
            'trash' => [$resource, ['label' => __('basics13::messages.trash_label'), 'url' => null]],
        };
    }

    /**
     * Whether a search term or an advanced filter narrows the list: an empty page then means that
     * nothing matched, not that nothing exists, so it says so and offers no "create" shortcut.
     */
    private function narrowed(string $search): bool
    {
        $filters = request()->query('filters');

        return $search !== '' || (is_array($filters) && $filters !== []);
    }

    /**
     * @param  'active'|'archived'|'trash'  $state
     */
    private function emptyMessage(string $state, bool $narrowed): string
    {
        if ($narrowed) {
            return $this->noMatchMessage();
        }

        return match ($state) {
            'active' => $this->noRecordsMessage(),
            'archived' => __('basics13::messages.no_archived_records'),
            'trash' => __('basics13::messages.trash_is_empty'),
        };
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function editAction(Model $record): array
    {
        return [
            'type' => 'form-modal',
            'label' => __('basics13::messages.edit'),
            'icon' => 'pencil-square',
            'test' => $this->resourceKey().'-edit-'.$this->key($record),
            'payload' => $this->editPayload($record),
        ];
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function deleteAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('basics13::messages.move_to_trash'),
            'trash',
            'delete',
            route($this->routes()['destroy'], $record),
            'DELETE',
            __('basics13::messages.delete_record', ['name' => $this->recordName($record)]),
            __('basics13::messages.you_can_restore_from_trash'),
            danger: true,
        );
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function archiveAction(Model $record, ?string $hint = null): array
    {
        $action = $this->confirmAction(
            $record,
            __('basics13::messages.archive'),
            'archive-box',
            'archive',
            route($this->routes()['archive'], $record),
            'PATCH',
            __('basics13::messages.archive_record', ['name' => $this->recordName($record)]),
            __('basics13::messages.you_can_activate_from_archived'),
            danger: true,
        );

        if ($hint !== null) {
            $action['hint'] = $hint;
        }

        return $action;
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function activateAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('basics13::messages.activate'),
            'archive-box-arrow-down',
            'activate',
            route($this->routes()['activate'], $record),
            'PATCH',
            __('basics13::messages.activate_record', ['name' => $this->recordName($record)]),
            __('basics13::messages.record_returns_to_active'),
            danger: false,
        );
    }

    /**
     * A trashed record goes back to the list its active flag sends it to.
     *
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function restoreAction(Model $record, bool $active): array
    {
        return $this->confirmAction(
            $record,
            __('basics13::messages.restore'),
            'arrow-path',
            'restore',
            route($this->routes()['restore'], $this->key($record)),
            'PATCH',
            __('basics13::messages.restore_record', ['name' => $this->recordName($record)]),
            $active
                ? __('basics13::messages.record_returns_to_active')
                : __('basics13::messages.record_returns_to_archived'),
            danger: false,
        );
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function forceDeleteAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('basics13::messages.delete_permanently'),
            'trash',
            'force-delete',
            route($this->routes()['trashDestroy'], $this->key($record)),
            'DELETE',
            __('basics13::messages.permanently_delete_record', ['name' => $this->recordName($record)]),
            __('basics13::messages.action_cannot_be_undone'),
            danger: true,
        );
    }

    /**
     * How the confirmation modal names the record, so the user sees which one the action hits.
     *
     * @param  TRecord  $record
     */
    protected function recordName(Model $record): string
    {
        return (string) $record->getAttribute('name');
    }

    /**
     * @return array{type: string, label: string, icon: string, test: string, hint: string}
     */
    final protected function blockedAction(string $label, string $icon, string $test, string $hint): array
    {
        return [
            'type' => 'blocked',
            'label' => $label,
            'icon' => $icon,
            'test' => $test,
            'hint' => $hint,
        ];
    }

    /**
     * @param  TRecord  $record
     * @return array{type: string, label: string, icon: string, test: string, danger: bool, action: string, method: string, confirmTitle: string, confirmText: string, confirmLabel: string}
     */
    private function confirmAction(
        Model $record,
        string $label,
        string $icon,
        string $action,
        string $url,
        string $method,
        string $confirmTitle,
        string $confirmText,
        bool $danger,
    ): array {
        return [
            'type' => 'confirm-modal',
            'label' => $label,
            'icon' => $icon,
            'test' => $this->resourceKey().'-'.$action.'-'.$this->key($record),
            'danger' => $danger,
            'action' => $url,
            'method' => $method,
            'confirmTitle' => $confirmTitle,
            'confirmText' => $confirmText,
            'confirmLabel' => $label,
        ];
    }

    /**
     * Key of the record as the data-test attributes spell it.
     */
    private function key(Model $record): string
    {
        $key = $record->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Translated placeholder of the search box. Shared by every resource: the box lives in the
     * common page skeleton, so it does not name the resource it filters.
     */
    protected function searchPlaceholder(): string
    {
        return __('basics13::messages.search_record');
    }

    /**
     * Translated plural name of the resource.
     */
    abstract protected function resourceLabel(): string;

    final protected function noMatchMessage(): string
    {
        return __('basics13::messages.no_matching_records');
    }

    final protected function noRecordsMessage(): string
    {
        return __('basics13::messages.no_records');
    }

    /**
     * Singular slug of the resource. It names the data-test attributes of the row actions, the
     * state tabs and the payload key the edit action carries.
     */
    abstract protected function resourceKey(): string;

    /**
     * Routes this transformer links to, indexed by list state and by action. Override when a
     * resource's route names do not follow the standard plural-prefix convention.
     *
     * @return array{active: string, archived: string, trash: string, destroy: string, archive: string, activate: string, restore: string, trashDestroy: string}
     */
    protected function routes(): array
    {
        $prefix = Str::plural($this->resourceKey());

        return [
            'active' => $prefix.'.index',
            'archived' => $prefix.'.archived.index',
            'trash' => $prefix.'.trash.index',
            'destroy' => $prefix.'.destroy',
            'archive' => $prefix.'.archive',
            'activate' => $prefix.'.archived.activate',
            'restore' => $prefix.'.trash.restore',
            'trashDestroy' => $prefix.'.trash.destroy',
        ];
    }

    /**
     * Fields the form needs to open in edit mode. Shared by the row name button and the row edit
     * action so both always open the drawer with the very same data.
     *
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    abstract protected function editPayload(Model $record): array;

    /**
     * Columns specific to the resource, without the extra date or actions.
     *
     * @param  TRecord  $record
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    abstract protected function columns(Model $record, array $extra): array;

    /**
     * Whether the delete action should become an archive action (active list only).
     *
     * @param  TRecord  $record
     */
    abstract protected function getDeleteBlockCondition(Model $record): bool;

    /**
     * Whether the force-delete action should be blocked in the trash list.
     *
     * @param  TRecord  $record
     */
    abstract protected function getForceDeleteBlockCondition(Model $record): bool;

    /**
     * Hint shown when force-delete is blocked in the trash list.
     *
     * @param  TRecord  $record
     */
    abstract protected function getForceDeleteBlockHint(Model $record): string;

    /**
     * Extra date column for the given scope. Defaults to created_at/updated_at/deleted_at.
     *
     * @param  TRecord  $record
     * @param  'active'|'archived'|'trash'  $scope
     * @return string|null ISO 8601 string or null
     */
    protected function getExtraDate(Model $record, string $scope): ?string
    {
        return match ($scope) {
            'active' => $record->getAttribute('created_at')?->toIso8601String(),
            'archived' => $record->getAttribute('updated_at')?->toIso8601String(),
            'trash' => $record->getAttribute('deleted_at')?->toIso8601String(),
        };
    }

    /**
     * Whether the trashed record should restore to active list (vs archived).
     * Defaults to the record's active flag.
     *
     * @param  TRecord  $record
     */
    protected function getRestoreActive(Model $record): bool
    {
        return (bool) $record->getAttribute('active');
    }

    /**
     * Active list view: rows with edit + delete/archive actions and created_at date.
     *
     * @param  LengthAwarePaginator<int, TRecord>  $records
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $records, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('active', $search, $counts),
            'rows' => collect($records->items())->map(function (Model $record): array {
                $extra = $this->integrationRow($record);

                return [
                    ...$this->columns($record, $extra),
                    'extraDate' => $this->getExtraDate($record, 'active'),
                    'actions' => [
                        $this->editAction($record),
                        $this->getDeleteBlockCondition($record)
                            ? $this->archiveAction($record, __('basics13::messages.archive_instead_of_delete'))
                            : $this->deleteAction($record),
                    ],
                ];
            })->all(),
        ];
    }

    /**
     * Archived list view: rows with activate action and updated_at date.
     *
     * @param  LengthAwarePaginator<int, TRecord>  $records
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function archived(LengthAwarePaginator $records, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('archived', $search, $counts),
            'rows' => collect($records->items())->map(function (Model $record): array {
                $extra = $this->integrationRow($record);

                return [
                    ...$this->columns($record, $extra),
                    'extraDate' => $this->getExtraDate($record, 'archived'),
                    'actions' => [$this->activateAction($record)],
                ];
            })->all(),
        ];
    }

    /**
     * Trash list view: rows with restore + force-delete/blocked actions and deleted_at date.
     *
     * @param  LengthAwarePaginator<int, TRecord>  $records
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $records, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('trash', $search, $counts),
            'rows' => collect($records->items())->map(function (Model $record): array {
                $extra = $this->integrationRow($record);

                return [
                    ...$this->columns($record, $extra),
                    'extraDate' => $this->getExtraDate($record, 'trash'),
                    'actions' => [
                        $this->restoreAction($record, $this->getRestoreActive($record)),
                        $this->getForceDeleteBlockCondition($record)
                            ? $this->blockedAction(
                                __('basics13::messages.delete_permanently'),
                                'lock-closed',
                                $this->resourceKey().'-force-delete-blocked-'.$this->key($record),
                                $this->getForceDeleteBlockHint($record),
                            )
                            : $this->forceDeleteAction($record),
                    ],
                ];
            })->all(),
        ];
    }

    /**
     * Get the integration's row data for a record. Override if no integration is used.
     *
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    protected function integrationRow(Model $record): array
    {
        return [];
    }
}
