<?php

namespace Basics13\Tests\Feature\Roles;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Basics13\Tests\Feature\FeatureTestCase;

/**
 * The role schema checks against MariaDB, where the unique indexes are generated columns rather
 * than partial indexes. The migrations run on BASICS13_MARIADB_DATABASE, which MUST be a database
 * of its own for the tests: they rebuild it from scratch.
 */
#[Group('mariadb')]
class MariaDbRoleSchemaTest extends FeatureTestCase
{
    use AssertsRoleSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $database = getenv('BASICS13_MARIADB_DATABASE');

        if ($database === false) {
            $this->markTestSkipped('Set BASICS13_MARIADB_DATABASE to run the MariaDB integration tests.');
        }

        $this->assertNotSame('', $database, 'BASICS13_MARIADB_DATABASE must name a test database.');

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => getenv('DB_HOST') ?: 'db',
            'database.connections.mysql.port' => (int) (getenv('DB_PORT') ?: 3306),
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => getenv('DB_USERNAME') ?: 'laravel',
            'database.connections.mysql.password' => getenv('DB_PASSWORD') ?: 'laravel',
        ]);

        Artisan::call('migrate:fresh', ['--database' => 'mysql']);
    }
}
