<?php

use Basics13\Enums\Language;
use Basics13\Enums\SettingType;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * The languages the application works in: the translated fields of every form show only these.
 * Basque by default. `insertOrIgnore` keeps it idempotent and never overwrites a changed value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('BAS_settings')->insertOrIgnore([
            'package' => 'basics13',
            'class' => Language::class,
            'variable' => 'languages',
            'type' => SettingType::Languages->value,
            'value' => Language::EU->value,
            'name_eu' => 'Hizkuntzak',
            'name_es' => 'Idiomas',
            'name_fr' => 'Langues',
            'name_en' => 'Languages',
            'description_eu' => 'Aplikazioak erabiltzen dituen hizkuntzak. Formularioek hizkuntza hauetako eremuak bakarrik erakusten dituzte.',
            'description_es' => 'Idiomas que utiliza la aplicación. Los formularios solo muestran los campos de estos idiomas.',
            'description_fr' => "Langues utilisées par l'application. Les formulaires n'affichent que les champs de ces langues.",
            'description_en' => 'Languages the application uses. Forms only show the fields of these languages.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('BAS_settings')->where('package', 'basics13')->where('variable', 'languages')->delete();
    }
};
