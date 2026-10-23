<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mensajes genéricos de resultado para recursos CRUD
    |--------------------------------------------------------------------------
    |
    | Estos mensajes son usados por los controladores y transformers base del
    | paquete para operaciones de crear, actualizar, archivar, papelera y
    | restaurar. Son agnósticos al recurso para que cualquier recurso los reutilice.
    |
    */

    'created' => 'Registro creado correctamente.',
    'updated' => 'Registro actualizado correctamente.',
    'moved_to_trash' => 'Registro movido a la papelera.',
    'permanently_deleted' => 'Registro eliminado definitivamente.',
    'restored' => 'Registro restaurado correctamente.',
    'restored_no_new_record' => 'Registro restaurado correctamente. No se ha creado otro registro con el nombre repetido.',
    'archived' => 'Registro archivado correctamente.',
    'activated' => 'Registro activado correctamente.',
    'not_in_trash' => 'Este registro ya no está en la papelera.',
    'cannot_restore_name_taken' => 'No se puede restaurar porque otro registro fuera de la papelera ya usa este nombre.',
    'deleted_name_conflict' => 'Un registro eliminado ya usa el nombre :name.',
    'cannot_delete_related' => 'No se puede eliminar porque tiene registros relacionados. Puedes archivarlo.',
    'cannot_force_delete_related' => 'No se puede eliminar definitivamente porque tiene registros relacionados.',
    'archive_instead_of_delete' => 'Archiva este registro en lugar de eliminarlo porque tiene registros relacionados.',

    // Botones de acción
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'move_to_trash' => 'Mover a la papelera',
    'delete_record' => '¿Mover «:name» a la papelera?',
    'archive' => 'Archivar',
    'archive_record' => '¿Archivar «:name»?',
    'activate' => 'Activar',
    'activate_record' => '¿Activar «:name»?',
    'restore' => 'Restaurar',
    'restore_record' => '¿Restaurar «:name»?',
    'delete_permanently' => 'Eliminar definitivamente',
    'permanently_delete_record' => '¿Eliminar «:name» definitivamente?',
    'action_cannot_be_undone' => 'Esta acción no se puede deshacer.',
    'search_record' => 'Buscar registro',

    // Mensajes de vistas de lista
    'created_at' => 'Creado el',
    'updated_at' => 'Actualizado el',
    'deleted_at' => 'Eliminado el',
    'archived_label' => 'Archivado',
    'trash_label' => 'Papelera',
    'active_tab' => 'Activos',
    'active_label' => 'Activo',
    'inactive_label' => 'Archivado',
    'export_to_excel' => 'Exportar a Excel',
    'no_archived_records' => 'No hay registros archivados.',
    'trash_is_empty' => 'La papelera está vacía.',
    'no_matching_records' => 'Ningún registro coincide con la búsqueda.',
    'no_records' => 'Todavía no hay registros.',
    'you_can_restore_from_trash' => 'Puedes restaurarlo desde la papelera.',
    'you_can_activate_from_archived' => 'Puedes activarlo de nuevo desde el listado de archivados.',
    'record_returns_to_active' => 'El registro volverá al listado de activos.',
    'record_returns_to_archived' => 'El registro volverá al listado de archivados.',
    'pagination_summary' => 'Mostrando :first a :last de :total resultados',

    // Búsqueda avanzada
    'close' => 'Cerrar',
    'advanced_search' => 'Búsqueda avanzada',
    'show_advanced_fields' => 'Mostrar campos avanzados',
    'hide_advanced_fields' => 'Ocultar campos avanzados',
    'clear_filters' => 'Limpiar filtros',
    'clear_all_filters' => 'Limpiar todos los filtros',
    'apply_filters' => 'Aplicar filtros',
    'remove_filter' => 'Eliminar filtro',
    'filter_operator' => 'Operador del filtro',
    'any_value' => 'Cualquiera',
    'select_multiple' => 'Seleccionar varios',
    'select_single' => 'Seleccionar uno',
    'select_placeholder' => 'Seleccionar...',
    'date_from' => 'Desde',
    'date_to' => 'Hasta',
    'is_empty' => '(vacío)',
    'operator_contains' => 'contiene',
    'operator_equals' => 'es igual a',
    'operator_not_equals' => 'no es igual a',
    'operator_starts_with' => 'empieza por',
    'operator_ends_with' => 'termina en',
    'operator_before' => 'antes de',
    'operator_after' => 'después de',
    'operator_between' => 'entre',
    'operator_is_empty' => 'está vacío',
    'operator_is_true' => 'Sí',
    'operator_is_false' => 'No',
    'operator_greater_than' => 'mayor que',
    'operator_less_than' => 'menor que',
    'operator_greater_than_or_equal' => 'mayor o igual que',
    'operator_less_than_or_equal' => 'menor o igual que',

    'own_account_only' => 'Solo el titular de la cuenta puede cambiar el correo y la contraseña.',

    // Impersonación
    'impersonation_started' => 'Ahora personificando a :name.',
    'impersonation_ended' => 'Personificación finalizada. Volviste a tu cuenta.',
    'login_locked' => 'Demasiados intentos fallidos. Vuelve a intentarlo dentro de :minutes minutos.',
    'no_active_impersonation' => 'No hay personificación activa para salir.',
    'impersonate_confirm_title' => '¿Personificar a :name?',
    'impersonate_confirm_text' => 'Iniciarás sesión como :name con sus permisos. Tu sesión original se guardará para poder volver.',
    'leave_impersonation' => 'Dejar impersonación',
    'impersonating_as' => 'personificando',
];
