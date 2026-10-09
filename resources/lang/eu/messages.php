<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CRUD baliabideen emaitza-mezu orokorrak
    |--------------------------------------------------------------------------
    |
    | Mezu hauek paketearen kontroladore eta transformadoreBasedeek erabiltzen
    | dituzte sortzeko, eguneratzeko, artxibatzeko, zakarrontzirabidatzeko eta
    | berreskuratzeko eragiketarako. Baliabide-agnostikoak dira, beraz, edozein
    | baliabideak berriro erabili ditzake.
    |
    */

    'created' => 'Erregistroa behar bezala sortu da.',
    'updated' => 'Erregistroa behar bezala eguneratu da.',
    'moved_to_trash' => 'Erregistroa zakarrontzira eraman da.',
    'permanently_deleted' => 'Erregistroa behin betiko ezabatu da.',
    'restored' => 'Erregistroa behar bezala berreskuratu da.',
    'restored_no_new_record' => 'Erregistroa behar bezala berreskuratu da. Ez da izen errepikatuarekin beste erregistrorik sortu.',
    'archived' => 'Erregistroa behar bezala artxibatu da.',
    'activated' => 'Erregistroa behar bezala aktibatu da.',
    'not_in_trash' => 'Erregistro hau ez dago jada zakarrontzian.',
    'cannot_restore_name_taken' => 'Ezinezkoa da berreskuratu beste erregistro bat zakarrontzian izan ez den izen hori duelako.',
    'deleted_name_conflict' => 'Ezabatutako erregistroak izen hori erabiltzen du: :name.',
    'cannot_delete_related' => 'Ezinezkoa da ezabatu lotutako erregistroak dituelako. Archibatu dezakezu',
    'cannot_force_delete_related' => 'Ezinezkoa da behin betiko ezabatu lotutako erregistroak dituelako.',

    // Ekintza-botoiak
    'edit' => 'Editatu',
    'delete' => 'Ezabatu',
    'delete_record' => 'Erregistroa ezabatu?',
    'archive' => 'Artxibatu',
    'archive_record' => 'Erregistroa artxibatu?',
    'activate' => 'Aktibatu',
    'activate_record' => 'Erregistroa aktibatu?',
    'restore' => 'Berreskuratu',
    'restore_record' => 'Erregistroa berreskuratu?',
    'delete_permanently' => 'Behin betiko ezabatu',
    'permanently_delete_record' => 'Erregistroa behin betiko ezabatu?',
    'action_cannot_be_undone' => 'Ekintza hau ezin da desegin.',
    'search_record' => 'Erregistroa bilatu',

    // Zerrenda-irudikapen mezuak
    'created_at' => 'Sortze data',
    'updated_at' => 'Eguneraketa data',
    'deleted_at' => 'Ezabaketa data',
    'archived_label' => 'Artxibatu',
    'trash_label' => 'Zakarrontzia',
    'no_archived_records' => 'Ez dago artxibatutako erregistrorik.',
    'trash_is_empty' => 'Zakarrontzia hutsa dago.',
    'you_can_restore_from_trash' => 'Zakarrontzitik berreskura dezakezu.',
    'you_can_activate_from_archived' => 'Artxibatueen zerrendatik aktibatu dezakezu berriro.',
    'record_returns_to_active' => 'Erregistroa aktibuen zerrendara itzuliko da.',
    'record_returns_to_archived' => 'Erregistroa artxibatuekin zerrendara itzuliko da.',
];
