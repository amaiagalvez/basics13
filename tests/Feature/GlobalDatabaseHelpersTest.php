<?php

namespace Basics13\Tests\Feature;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GlobalDatabaseHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_column_helpers_create_common_and_audit_columns(): void
    {
        Schema::create('global_helper_records', static function (Blueprint $table): void {
            $table->id();
            addCommonColumns($table);
            addAuditColumns($table);
        });

        $columns = Schema::getColumnListing('global_helper_records');

        foreach (['notes', 'active', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'] as $column) {
            $this->assertContains($column, $columns);
        }

        $foreignKeys = Schema::getForeignKeys('global_helper_records');
        $this->assertEqualsCanonicalizing(
            ['created_by', 'updated_by', 'deleted_by'],
            array_merge(...array_column($foreignKeys, 'columns')),
        );
        $this->assertSame(['users', 'users', 'users'], array_column($foreignKeys, 'foreign_table'));
    }

    public function test_global_unique_index_allows_trashed_names_and_separate_scopes(): void
    {
        $this->createScopedTable();
        DB::table('global_scoped_records')->insert([
            ['name' => 'Shared name', 'tenant_id' => 1, 'deleted_at' => null],
            ['name' => 'Shared name', 'tenant_id' => 2, 'deleted_at' => null],
            ['name' => 'Shared name', 'tenant_id' => 1, 'deleted_at' => now()],
        ]);

        $this->assertSame(3, DB::table('global_scoped_records')->count());
    }

    public function test_global_unique_index_rejects_duplicate_active_names_in_the_same_scope(): void
    {
        $this->createScopedTable();
        DB::table('global_scoped_records')->insert(['name' => 'Shared name', 'tenant_id' => 1]);

        $this->expectException(QueryException::class);

        DB::table('global_scoped_records')->insert(['name' => 'Shared name', 'tenant_id' => 1]);
    }

    private function createScopedTable(): void
    {
        Schema::create('global_scoped_records', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id');
            addCommonColumns($table);
        });

        addUniqueActiveNameIndex('global_scoped_records', ['tenant_id']);
    }
}
