<?php

namespace Basics13\Tests\Unit\Support\Database;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Events\QueryExecuted;
use PHPUnit\Framework\Attributes\DataProvider;

class DatabaseHelpersTest extends TestCase
{
    #[DataProvider('databaseDrivers')]
    public function test_detects_sqlite_or_pgsql_on_the_default_connection(string $driver, bool $expected): void
    {
        $originalDefault = config('database.default');
        $originalConnection = config('database.connections.helper-test');

        try {
            config([
                'database.default' => 'helper-test',
                'database.connections.helper-test' => [
                    'driver' => $driver,
                    'database' => ':memory:',
                ],
            ]);

            $result = isSqliteOrPgsql();

            $this->assertSame($expected, $result);
        } finally {
            config([
                'database.default' => $originalDefault,
                'database.connections.helper-test' => $originalConnection,
            ]);
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function databaseDrivers(): array
    {
        return [
            'sqlite' => ['sqlite', true],
            'postgresql' => ['pgsql', true],
            'mysql' => ['mysql', false],
            'mariadb' => ['mariadb', false],
            'sql-server' => ['sqlsrv', false],
        ];
    }

    public function test_add_common_columns_adds_expected_columns(): void
    {
        Schema::create('test_helpers', function (Blueprint $table) {
            $table->id();
            addCommonColumns($table);
        });

        $columns = Schema::getColumnListing('test_helpers');

        $this->assertContains('notes', $columns);
        $this->assertContains('active', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
        // deleted_at is added by softDeletes() inside addCommonColumns
        $this->assertContains('deleted_at', $columns);
    }

    public function test_add_audit_columns_adds_expected_columns(): void
    {
        Schema::create('test_audit', function (Blueprint $table) {
            $table->id();
            addAuditColumns($table);
        });

        $columns = Schema::getColumnListing('test_audit');

        $this->assertContains('created_by', $columns);
        $this->assertContains('updated_by', $columns);
        $this->assertContains('deleted_by', $columns);
    }

    public function test_add_unique_active_name_index_creates_index_for_sqlite(): void
    {
        $statement = null;

        DB::listen(static function (QueryExecuted $query) use (&$statement): void {
            if (str_starts_with($query->sql, 'CREATE UNIQUE INDEX')) {
                $statement = $query->sql;
            }
        });

        Schema::create('CUM_test_unique_index', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // addCommonColumns adds deleted_at which is required for the partial index
            addCommonColumns($table);
        });

        addUniqueActiveNameIndex('CUM_test_unique_index');

        $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='CUM_test_unique_index'");
        $this->assertNotEmpty($indexes);
        $this->assertSame(
            'CREATE UNIQUE INDEX "CUM_test_unique_index_active_name_unique" ON "CUM_test_unique_index" ("name") WHERE "deleted_at" IS NULL',
            $statement,
        );
    }

    public function test_add_unique_active_name_index_with_scope(): void
    {
        Schema::create('test_unique_index_scoped', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id');
            // addCommonColumns adds deleted_at which is required for the partial index
            addCommonColumns($table);
        });

        addUniqueActiveNameIndex('test_unique_index_scoped', ['tenant_id']);

        $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='test_unique_index_scoped'");
        $this->assertNotEmpty($indexes);
    }
}
