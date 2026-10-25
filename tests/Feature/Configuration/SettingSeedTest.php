<?php

namespace Basics13\Tests\Feature\Configuration;

use Basics13\Models\Setting;
use Basics13\Enums\SettingType;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingSeedTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_basics13_variables(): void
    {
        $this->assertSame(4, Setting::query()->count());
        $this->assertEqualsCanonicalizing(
            ['alert_email', 'languages', 'lockout_minutes', 'max_login_attempts'],
            Setting::query()->pluck('variable')->all(),
        );

        $maxAttempts = Setting::query()->where('variable', 'max_login_attempts')->firstOrFail();
        $this->assertSame(SettingType::Integer, $maxAttempts->type);
        $this->assertSame('3', $maxAttempts->value);
        $this->assertSame('15', Setting::query()->where('variable', 'lockout_minutes')->value('value'));
        $this->assertSame('', Setting::query()->where('variable', 'alert_email')->value('value'));
        $this->assertSame('eu', Setting::query()->where('variable', 'languages')->value('value'));

        foreach (['eu', 'es', 'fr', 'en'] as $locale) {
            $this->assertNotEmpty($maxAttempts->getAttribute('name_'.$locale));
            $this->assertNotEmpty($maxAttempts->getAttribute('description_'.$locale));
        }
    }

    public function test_seeding_again_duplicates_nothing_and_keeps_a_changed_value(): void
    {
        $maxAttempts = Setting::query()->where('variable', 'max_login_attempts')->firstOrFail();
        $maxAttempts->value = '5';
        $maxAttempts->save();

        $this->runSeedMigration();
        $this->runSeedMigration();

        $this->assertSame(4, Setting::query()->count());
        $this->assertSame('5', Setting::query()->where('variable', 'max_login_attempts')->value('value'));
    }

    private function runSeedMigration(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_23_000002_seed_settings.php';

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            $this->fail('The seed migration must define up().');
        }

        $migration->up();
    }
}
