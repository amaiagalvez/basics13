<?php

namespace Basics13\Tests\Feature;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use Basics13\Support\Database\UniqueConstraintViolation;

/**
 * Temporary tables isolate these tests without migrating or refreshing a shared database.
 *
 * The connection reads DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD and falls back to the
 * Compose service defaults, so it reaches the database both from the tooling Compose network and
 * from testing13/phpunit.sh, which runs the container on the host network against a published port.
 */
#[Group('mariadb')]
class MariaDbUniqueActiveNameIndexTest extends TestCase
{
    private bool $tableCreated = false;

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
    }

    protected function tearDown(): void
    {
        try {
            if ($this->tableCreated) {
                DB::statement('DROP TEMPORARY TABLE basics13_unique_index_test');
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @param list<string> $scope */
    #[DataProvider('indexScopes')]
    public function test_index_rejects_duplicate_active_names(array $scope): void
    {
        $this->createTable($scope);
        DB::table('basics13_unique_index_test')->insert(['name' => 'Shared name', 'tenant_id' => 1]);

        try {
            DB::table('basics13_unique_index_test')->insert(['name' => 'Shared name', 'tenant_id' => 1]);
            $this->fail('The generated unique index must reject duplicate active names.');
        } catch (QueryException $exception) {
            $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
            $this->assertSame(1, DB::table('basics13_unique_index_test')->count());
        }
    }

    /** @param list<string> $scope */
    #[DataProvider('indexScopes')]
    public function test_index_allows_name_reuse_after_soft_deletion_and_rejects_conflicting_restores(array $scope): void
    {
        $this->createTable($scope);
        $trashedId = DB::table('basics13_unique_index_test')->insertGetId([
            'name' => 'Shared name', 'tenant_id' => 1, 'deleted_at' => now(),
        ]);
        DB::table('basics13_unique_index_test')->insert(['name' => 'Shared name', 'tenant_id' => 1]);

        $this->assertSame(2, DB::table('basics13_unique_index_test')->count());
        $this->assertNull(DB::table('basics13_unique_index_test')->where('id', $trashedId)->value('active_name'));

        try {
            DB::table('basics13_unique_index_test')->where('id', $trashedId)->update(['deleted_at' => null]);
            $this->fail('Restoring a duplicate name must violate the generated unique index.');
        } catch (QueryException $exception) {
            $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
            $this->assertNotNull(DB::table('basics13_unique_index_test')->where('id', $trashedId)->value('deleted_at'));
        }
    }

    public function test_scoped_index_allows_the_same_name_in_different_scopes(): void
    {
        $this->createTable(['tenant_id']);
        DB::table('basics13_unique_index_test')->insert([
            ['name' => 'Shared name', 'tenant_id' => 1],
            ['name' => 'Shared name', 'tenant_id' => 2],
        ]);

        $this->assertSame(2, DB::table('basics13_unique_index_test')->count());
    }

    /** @param list<string> $scope */
    #[DataProvider('collationVariants')]
    public function test_index_rejects_case_and_accent_variants_on_insert_and_restore(array $scope, string $variant): void
    {
        $this->createTable($scope);
        $trashedId = DB::table('basics13_unique_index_test')->insertGetId([
            'name' => $variant, 'tenant_id' => 1, 'deleted_at' => now(),
        ]);
        DB::table('basics13_unique_index_test')->insert(['name' => 'Mikel', 'tenant_id' => 1]);

        try {
            DB::table('basics13_unique_index_test')->insert(['name' => $variant, 'tenant_id' => 1]);
            $this->fail('Case and accent variants must collide under utf8mb4_unicode_ci.');
        } catch (QueryException $exception) {
            $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
        }

        try {
            DB::table('basics13_unique_index_test')->where('id', $trashedId)->update(['deleted_at' => null]);
            $this->fail('Restoring a case or accent variant must violate the unique index.');
        } catch (QueryException $exception) {
            $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
        }

        $this->assertSame(2, DB::table('basics13_unique_index_test')->count());
        $this->assertNotNull(DB::table('basics13_unique_index_test')->where('id', $trashedId)->value('deleted_at'));
    }

    /** @return array<string, array{list<string>, string}> */
    public static function collationVariants(): array
    {
        return [
            'unscoped case' => [[], 'mikel'],
            'unscoped accent' => [[], 'Míkel'],
            'scoped case' => [['tenant_id'], 'mikel'],
            'scoped accent' => [['tenant_id'], 'Míkel'],
        ];
    }

    /** @return array<string, array{list<string>}> */
    public static function indexScopes(): array
    {
        return [
            'unscoped' => [[]],
            'scoped' => [['tenant_id']],
        ];
    }

    /** @param list<string> $scope */
    private function createTable(array $scope): void
    {
        Schema::create('basics13_unique_index_test', static function (Blueprint $table): void {
            $table->temporary();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id');
            addCommonColumns($table);
        });
        $this->tableCreated = true;

        addUniqueActiveNameIndex('basics13_unique_index_test', $scope);
    }
}
