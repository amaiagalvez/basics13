<?php

namespace Basics13\Tests\Feature;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Basics13\Support\Database\Helpers;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use Basics13\Support\Database\UniqueConstraintViolation;

/**
 * Temporary tables isolate these tests without migrating or refreshing a shared database.
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
            'database.connections.mysql.database' => $database,
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
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id');
            Helpers::addCommonColumns($table);
        });
        $this->tableCreated = true;

        Helpers::addUniqueActiveNameIndex('basics13_unique_index_test', $scope);
    }
}
