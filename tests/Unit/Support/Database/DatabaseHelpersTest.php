<?php

namespace Basics13\Tests\Unit\Support\Database;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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

    /**
     * SQLite and PostgreSQL use a partial index, MySQL and SQL Server need a stored generated
     * column instead. The MariaDB integration test proves the real DDL; here the schema builder and
     * the connection are swapped so the branch runs without a MySQL server.
     */
    public function test_add_unique_active_column_index_generates_a_stored_column_outside_sqlite_and_pgsql(): void
    {
        $connection = $this->app['db']->connection();

        $schema = new class
        {
            /** @var array{0: string, 1: \Closure}|null */
            public ?array $captured = null;

            public function table(string $table, \Closure $callback): void
            {
                $this->captured = [$table, $callback];
            }
        };

        $manager = new class
        {
            public object $connection;

            public function connection(): object
            {
                return $this->connection;
            }
        };

        $manager->connection = new class
        {
            public function getDriverName(): string
            {
                return 'mysql';
            }
        };

        $originalSchema = Schema::getFacadeRoot();
        $originalManager = DB::getFacadeRoot();

        Schema::swap($schema);
        DB::swap($manager);

        try {
            addUniqueActiveColumnIndex('ENT_generated_index', 'name', ['tenant_id']);
        } finally {
            Schema::swap($originalSchema);
            DB::swap($originalManager);
        }

        $this->assertNotNull($schema->captured);

        [$table, $callback] = $schema->captured;
        $blueprint = new Blueprint($connection, $table, $callback);

        $generated = collect($blueprint->getColumns())
            ->first(static fn ($column): bool => $column->get('name') === 'active_name');

        $this->assertNotNull($generated);
        $this->assertTrue($generated->get('nullable'));
        $this->assertSame('IF(deleted_at IS NULL, name, NULL)', $generated->get('storedAs'));

        $unique = collect($blueprint->getCommands())->firstWhere('name', 'unique');

        $this->assertNotNull($unique);
        $this->assertSame(['tenant_id', 'active_name'], $unique['columns']);
        $this->assertSame('ENT_generated_index_tenant_id_active_name_unique', $unique['index']);
    }
}
