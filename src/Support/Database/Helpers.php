<?php

namespace Basics13\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Database helper functions for migrations.
 *
 * These functions are also available as global helpers when the package's autoload.files
 * includes this file. Use the static class methods for explicit calls, or the global
 * functions for brevity in migration files.
 */
final class Helpers
{
    /**
     * Check if the database driver is SQLite or PostgreSQL.
     */
    public static function isSqliteOrPgsql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true);
    }

    /**
     * Add the columns shared by the application's soft-deleting tables.
     */
    public static function addCommonColumns(Blueprint $table): void
    {
        $table->longText('notes')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
        $table->softDeletes();

        $table->index(['deleted_at', 'id']);
    }

    /**
     * Add the three audit columns that record who created, last changed and trashed a row.
     *
     * They are written by `Basics13\Concerns\TracksAuditColumns`, never by a request payload, so a record
     * keeps its trail while the user who made it exists. Foreign keys restrict deleting a user while
     * an audit record still points to them.
     */
    public static function addAuditColumns(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users');
        $table->foreignId('updated_by')->nullable()->constrained('users');
        $table->foreignId('deleted_by')->nullable()->constrained('users');
    }

    /**
     * Make a table's name unique among the rows that are not soft-deleted.
     *
     * SQLite and PostgreSQL get a partial index. MySQL and MariaDB have no partial indexes, so a
     * generated column carries the name only while the row is active: a unique index ignores the
     * several NULLs it leaves behind on the trashed rows that repeat an active name.
     *
     * @param  list<string>  $scope  Extra columns the name is unique within, besides [name] itself
     */
    public static function addUniqueActiveNameIndex(string $table, array $scope = []): void
    {
        $index = implode('_', [$table, ...$scope, 'active_name', 'unique']);

        if (self::isSqliteOrPgsql()) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s) WHERE deleted_at IS NULL',
                $index,
                $table,
                implode(', ', [...$scope, 'name']),
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index, $scope): void {
            $blueprint->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
            $blueprint->unique([...$scope, 'active_name'], $index);
        });
    }
}