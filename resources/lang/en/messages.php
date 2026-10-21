<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Generic outcome messages for CRUD resources
    |--------------------------------------------------------------------------
    |
    | These messages are used by the package's base controllers and transformers
    | for create, update, archive, trash, and restore operations. They are
    | resource-agnostic so any resource can reuse them.
    |
    */

    'created' => 'Record created successfully.',
    'updated' => 'Record updated successfully.',
    'moved_to_trash' => 'Record moved to trash.',
    'permanently_deleted' => 'Record permanently deleted.',
    'restored' => 'Record restored successfully.',
    'restored_no_new_record' => 'Record restored successfully. No new record was created with the repeated name.',
    'archived' => 'Record archived successfully.',
    'activated' => 'Record activated successfully.',
    'not_in_trash' => 'This record is no longer in the trash.',
    'cannot_restore_name_taken' => 'Cannot be restored because another record outside the trash uses this name.',
    'deleted_name_conflict' => 'A deleted record already uses the name :name.',
    'cannot_delete_related' => 'Cannot be deleted while it has related records.',
    'cannot_force_delete_related' => 'Cannot be permanently deleted while it has related records.',
    'archive_instead_of_delete' => 'Archive this record instead of deleting it because it has related records.',

    // Action buttons
    'edit' => 'Edit',
    'delete' => 'Delete',
    'delete_record' => 'Delete record?',
    'archive' => 'Archive',
    'archive_record' => 'Archive record?',
    'activate' => 'Activate',
    'activate_record' => 'Activate record?',
    'restore' => 'Restore',
    'restore_record' => 'Restore record?',
    'delete_permanently' => 'Delete permanently',
    'permanently_delete_record' => 'Permanently delete record?',
    'action_cannot_be_undone' => 'This action cannot be undone.',
    'search_record' => 'Search record',

    // List view messages
    'created_at' => 'Created at',
    'updated_at' => 'Updated at',
    'deleted_at' => 'Deleted at',
    'archived_label' => 'Archived',
    'trash_label' => 'Trash',
    'no_archived_records' => 'No archived records.',
    'trash_is_empty' => 'Trash is empty.',
    'you_can_restore_from_trash' => 'You can restore it from the trash.',
    'you_can_activate_from_archived' => 'You can activate it from the archived list.',
    'record_returns_to_active' => 'The record will return to the active list.',
    'record_returns_to_archived' => 'The record will return to the archived list.',
    'pagination_summary' => 'Showing :first to :last of :total results',

    // Advanced search
    'close' => 'Close',
    'advanced_search' => 'Advanced search',
    'show_advanced_fields' => 'Show advanced fields',
    'hide_advanced_fields' => 'Hide advanced fields',
    'clear_filters' => 'Clear filters',
    'clear_all_filters' => 'Clear all filters',
    'apply_filters' => 'Apply filters',
    'remove_filter' => 'Remove filter',
    'filter_operator' => 'Filter operator',
    'boolean_value' => 'Boolean value',
    'select_multiple' => 'Select multiple',
    'select_single' => 'Select one',
    'select_placeholder' => 'Select...',
    'date_from' => 'From',
    'date_to' => 'To',
    'true' => 'Yes',
    'false' => 'No',
    'is_empty' => '(empty)',
    'operator_contains' => 'contains',
    'operator_equals' => 'equals',
    'operator_not_equals' => 'does not equal',
    'operator_starts_with' => 'starts with',
    'operator_ends_with' => 'ends with',
    'operator_before' => 'before',
    'operator_after' => 'after',
    'operator_between' => 'between',
    'operator_is_empty' => 'is empty',
    'operator_is_true' => 'is true',
    'operator_is_false' => 'is false',
    'operator_greater_than' => 'greater than',
    'operator_less_than' => 'less than',
    'operator_greater_than_or_equal' => 'greater than or equal',
    'operator_less_than_or_equal' => 'less than or equal',
    'field_type_text' => 'Text',
    'field_type_date' => 'Date',
    'field_type_boolean' => 'Boolean',
    'field_type_number' => 'Number',
    'field_type_select' => 'Selection',
];
