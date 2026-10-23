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
    'move_to_trash' => 'Move to trash',
    'delete_record' => 'Move “:name” to the trash?',
    'archive' => 'Archive',
    'archive_record' => 'Archive “:name”?',
    'activate' => 'Activate',
    'activate_record' => 'Activate “:name”?',
    'restore' => 'Restore',
    'restore_record' => 'Restore “:name”?',
    'delete_permanently' => 'Delete permanently',
    'permanently_delete_record' => 'Permanently delete “:name”?',
    'action_cannot_be_undone' => 'This action cannot be undone.',
    'search_record' => 'Search record',

    // List view messages
    'created_at' => 'Created at',
    'updated_at' => 'Updated at',
    'deleted_at' => 'Deleted at',
    'archived_label' => 'Archived',
    'trash_label' => 'Trash',
    'active_tab' => 'Active',
    'active_label' => 'Active',
    'inactive_label' => 'Archived',
    'export_to_excel' => 'Export to Excel',
    'no_archived_records' => 'No archived records.',
    'trash_is_empty' => 'Trash is empty.',
    'no_matching_records' => 'No records match your search.',
    'no_records' => 'No records yet.',
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
    'any_value' => 'Any',
    'select_multiple' => 'Select multiple',
    'select_single' => 'Select one',
    'select_placeholder' => 'Select...',
    'date_from' => 'From',
    'date_to' => 'To',
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
    'operator_is_true' => 'Yes',
    'operator_is_false' => 'No',
    'operator_greater_than' => 'greater than',
    'operator_less_than' => 'less than',
    'operator_greater_than_or_equal' => 'greater than or equal',
    'operator_less_than_or_equal' => 'less than or equal',

    'own_account_only' => 'Only the account holder can change the email and the password.',

    // Impersonation
    'impersonation_started' => 'Now impersonating :name.',
    'impersonation_ended' => 'Impersonation ended. Back to your account.',
    'login_locked' => 'Too many failed attempts. Try again in :minutes minutes.',
    'no_active_impersonation' => 'No active impersonation to leave.',
    'impersonate_confirm_title' => 'Impersonate :name?',
    'impersonate_confirm_text' => 'You will be logged in as :name with their permissions. Your original session will be saved so you can return.',
    'leave_impersonation' => 'Leave impersonation',
    'impersonating_as' => 'impersonating',
];
