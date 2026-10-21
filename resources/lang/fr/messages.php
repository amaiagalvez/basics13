<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Messages génériques de résultat pour les ressources CRUD
    |--------------------------------------------------------------------------
    |
    | Ces messages sont utilisés par les contrôleurs et transformers de base du
    | paquet pour les opérations de création, mise à jour, archivage, corbeille
    | et restauration. Ils sont agnostiques à la ressource pour que toute
    | ressource puisse les réutiliser.
    |
    */

    'created' => 'Enregistrement créé avec succès.',
    'updated' => 'Enregistrement mis à jour avec succès.',
    'moved_to_trash' => 'Enregistrement déplacé vers la corbeille.',
    'permanently_deleted' => 'Enregistrement supprimé définitivement.',
    'restored' => 'Enregistrement restauré avec succès.',
    'restored_no_new_record' => 'Enregistrement restauré avec succès. Aucun nouvel enregistrement n\'a été créé avec le nom répété.',
    'archived' => 'Enregistrement archivé avec succès.',
    'activated' => 'Enregistrement activé avec succès.',
    'not_in_trash' => 'Cet enregistrement n\'est plus dans la corbeille.',
    'cannot_restore_name_taken' => 'Impossible de restaurer car un autre enregistrement hors de la corbeille utilise déjà ce nom.',
    'deleted_name_conflict' => 'Un enregistrement supprimé utilise déjà le nom :name.',
    'cannot_delete_related' => 'Ne peut pas être supprimé car il a des enregistrements liés.',
    'cannot_force_delete_related' => 'Ne peut pas être supprimé définitivement car il a des enregistrements liés.',
    'archive_instead_of_delete' => 'Archivez cet enregistrement au lieu de le supprimer car il a des enregistrements liés.',

    // Boutons d'action
    'edit' => 'Editer',
    'delete' => 'Supprimer',
    'delete_record' => 'Supprimer l\'enregistrement ?',
    'archive' => 'Archiver',
    'archive_record' => 'Archiver l\'enregistrement ?',
    'activate' => 'Activer',
    'activate_record' => 'Activer l\'enregistrement ?',
    'restore' => 'Restaurer',
    'restore_record' => 'Restaurer l\'enregistrement ?',
    'delete_permanently' => 'Supprimer définitivement',
    'permanently_delete_record' => 'Supprimer l\'enregistrement définitivement ?',
    'action_cannot_be_undone' => 'Cette action ne peut pas être annulée.',
    'search_record' => 'Rechercher un enregistrement',

    // Messages des vues de liste
    'created_at' => 'Date de création',
    'updated_at' => 'Date de mise à jour',
    'deleted_at' => 'Date de suppression',
    'archived_label' => 'Archivé',
    'trash_label' => 'Corbeille',
    'no_archived_records' => 'Aucun enregistrement archivé.',
    'trash_is_empty' => 'La corbeille est vide.',
    'you_can_restore_from_trash' => 'Vous pouvez le restaurer depuis la corbeille.',
    'you_can_activate_from_archived' => 'Vous pouvez l\'activer depuis la liste des archivés.',
    'record_returns_to_active' => 'L\'enregistrement retournera à la liste active.',
    'record_returns_to_archived' => 'L\'enregistrement retournera à la liste des archivés.',
    'pagination_summary' => 'Affichage de :first à :last sur :total résultats',

    // Recherche avancée
    'close' => 'Fermer',
    'advanced_search' => 'Recherche avancée',
    'show_advanced_fields' => 'Afficher les champs avancés',
    'hide_advanced_fields' => 'Masquer les champs avancés',
    'clear_filters' => 'Effacer les filtres',
    'clear_all_filters' => 'Effacer tous les filtres',
    'apply_filters' => 'Appliquer les filtres',
    'remove_filter' => 'Supprimer le filtre',
    'filter_operator' => 'Opérateur du filtre',
    'any_value' => 'Tous',
    'select_multiple' => 'Sélectionner plusieurs',
    'select_single' => 'Sélectionner une option',
    'select_placeholder' => 'Sélectionner...',
    'date_from' => 'Depuis',
    'date_to' => 'Jusqu\'au',
    'is_empty' => '(vide)',
    'operator_contains' => 'contient',
    'operator_equals' => 'est égal à',
    'operator_not_equals' => 'n\'est pas égal à',
    'operator_starts_with' => 'commence par',
    'operator_ends_with' => 'se termine par',
    'operator_before' => 'avant',
    'operator_after' => 'après',
    'operator_between' => 'entre',
    'operator_is_empty' => 'est vide',
    'operator_is_true' => 'Oui',
    'operator_is_false' => 'Non',
    'operator_greater_than' => 'supérieur à',
    'operator_less_than' => 'inférieur à',
    'operator_greater_than_or_equal' => 'supérieur ou égal à',
    'operator_less_than_or_equal' => 'inférieur ou égal à',
    'field_type_text' => 'Texte',
    'field_type_date' => 'Date',
    'field_type_boolean' => 'Booléen',
    'field_type_number' => 'Nombre',
    'field_type_select' => 'Sélection',
];
