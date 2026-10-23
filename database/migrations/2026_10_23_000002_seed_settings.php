<?php

use Basics13\Auth\LoginAudit;
use Basics13\Enums\SettingType;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * The configuration variables of basics13: how many failed attempts lock a login, for how long,
 * and where a failure spike is alerted. A migration rather than a seeder: it has to run on every
 * deploy and under RefreshDatabase. `insertOrIgnore` keeps it idempotent and never overwrites a
 * value somebody already changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('BAS_settings')->insertOrIgnore([
            [
                'package' => 'basics13',
                'class' => LoginAudit::class,
                'variable' => 'max_login_attempts',
                'type' => SettingType::Integer->value,
                'value' => '3',
                'name_eu' => 'Saio-hasiera saiakera gehienez',
                'name_es' => 'Intentos máximos de acceso',
                'name_fr' => 'Tentatives de connexion maximales',
                'name_en' => 'Maximum login attempts',
                'description_eu' => 'Login berdinarekin jarraian onartzen dira huts egiteak, blokeatu aurretik.',
                'description_es' => 'Fallos seguidos con el mismo login antes de bloquearlo.',
                'description_fr' => 'Échecs consécutifs avec le même identifiant avant son blocage.',
                'description_en' => 'Consecutive failures with the same login before it is locked.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'package' => 'basics13',
                'class' => LoginAudit::class,
                'variable' => 'lockout_minutes',
                'type' => SettingType::Integer->value,
                'value' => '15',
                'name_eu' => 'Blokeo iraupena (minutu)',
                'name_es' => 'Minutos de bloqueo',
                'name_fr' => 'Minutes de blocage',
                'name_en' => 'Lockout minutes',
                'description_eu' => 'Login bat blokeatuta egongo den minutuak, saiakera gehienezko kopurura iritsi ondoren.',
                'description_es' => 'Minutos que un login permanece bloqueado tras alcanzar el máximo de intentos.',
                'description_fr' => 'Minutes pendant lesquelles un identifiant reste bloqué après le maximum de tentatives.',
                'description_en' => 'Minutes a login stays locked after reaching the maximum attempts.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'package' => 'basics13',
                'class' => LoginAudit::class,
                'variable' => 'alert_email',
                'type' => SettingType::Email->value,
                'value' => '',
                'name_eu' => 'Abisuaren posta',
                'name_es' => 'Correo de aviso',
                'name_fr' => "E-mail d'alerte",
                'name_en' => 'Alert email',
                'description_eu' => 'Saio-hasiera hutsegiteek gora egiten dutenean abisua jasoko duen helbidea. Hutsik, postarik ez da bidaltzen.',
                'description_es' => 'Dirección que recibe el aviso cuando los fallos de acceso se disparan. Vacío, no se envía correo.',
                'description_fr' => "Adresse qui reçoit l'alerte quand les échecs de connexion explosent. Vide, aucun e-mail n'est envoyé.",
                'description_en' => 'Address that receives the alert when failed logins spike. Empty, no email is sent.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        // The table these rows live in is dropped by the migration that created it.
    }
};
