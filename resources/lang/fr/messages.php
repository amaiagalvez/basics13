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
];
