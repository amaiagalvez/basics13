<?php

namespace Basics13\Tests\Feature\Support\Database;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Basics13\Tests\Fixtures\AuditActor;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class DatabaseHelpersTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);
    }

    public function test_adds_the_common_columns_to_a_table(): void
    {
        Schema::dropIfExists('helper_columns');
        Schema::create('helper_columns', function (Blueprint $table): void {
            $table->id();

            addCommonColumns($table);
        });

        $records = DB::table('helper_columns');
        $id = $records->insertGetId(['notes' => 'first notes']);

        $this->assertTrue(Schema::hasColumns('helper_columns', [
            'notes', 'active', 'created_at', 'updated_at', 'deleted_at',
        ]));
        $this->assertSame('first notes', $records->where('id', $id)->value('notes'));
        $this->assertEquals(1, $records->where('id', $id)->value('active'));
        $this->assertNull($records->where('id', $id)->value('deleted_at'));
        $this->assertTrue(Schema::hasIndex('helper_columns', ['deleted_at', 'id']));

        // Notes are nullable, so an insert that leaves the column out still succeeds.
        $secondId = $records->insertGetId(['active' => false]);
        $this->assertNull($records->where('id', $secondId)->value('notes'));

        Schema::drop('helper_columns');
    }

    public function test_adds_the_audit_columns_to_a_table(): void
    {
        Schema::dropIfExists('helper_audit_columns');
        Schema::create('helper_audit_columns', function (Blueprint $table): void {
            $table->id();

            addAuditColumns($table);
        });

        $this->assertTrue(Schema::hasColumns('helper_audit_columns', [
            'created_by', 'updated_by', 'deleted_by',
        ]));

        $author = AuditActor::factory()->create();
        $id = DB::table('helper_audit_columns')->insertGetId(['created_by' => $author->id]);

        // Every key is nullable, so a row written without an actor exists instead of failing.
        $anonymousId = DB::table('helper_audit_columns')->insertGetId([]);

        $this->assertSame(
            $author->id,
            DB::table('helper_audit_columns')->where('id', $id)->value('created_by'),
        );
        $this->assertNull(DB::table('helper_audit_columns')->where('id', $anonymousId)->value('created_by'));

        try {
            $author->forceDelete();
            self::fail('A user with audit references must not be force deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $author->id]);
        }

        $this->assertSame(
            $author->id,
            DB::table('helper_audit_columns')->where('id', $id)->value('created_by'),
        );

        Schema::drop('helper_audit_columns');
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

        Schema::create('BAS_test_unique_index', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // addCommonColumns adds deleted_at which is required for the partial index
            addCommonColumns($table);
        });

        addUniqueActiveNameIndex('BAS_test_unique_index');

        $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='BAS_test_unique_index'");
        $this->assertNotEmpty($indexes);
        $this->assertSame(
            'CREATE UNIQUE INDEX "BAS_test_unique_index_active_name_unique" ON "BAS_test_unique_index" ("name") WHERE "deleted_at" IS NULL',
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
