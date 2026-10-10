<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Check if the database driver is SQLite or PostgreSQL.
 */
function isSqliteOrPgsql(): bool
{
    $driver = DB::connection()->getDriverName();

    return in_array($driver, ['sqlite', 'pgsql'], true);
}

/**
 * Add the columns shared by the application's soft-deleting tables.
 */
function addCommonColumns(Blueprint $table): void
{
    $table->longText('notes')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['deleted_at', 'id']);
}

/**
 * Add the three audit columns that record who created, last changed and trashed a row.
 */
function addAuditColumns(Blueprint $table): void
{
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->foreignId('updated_by')->nullable()->constrained('users');
    $table->foreignId('deleted_by')->nullable()->constrained('users');
}

/**
 * Make a table's name unique among the rows that are not soft-deleted.
 *
 * @param  list<string>  $scope  Extra columns the name is unique within, besides [name] itself
 */
function addUniqueActiveNameIndex(string $table, array $scope = []): void
{
    $index = implode('_', array_merge([$table], $scope, ['active_name', 'unique']));

    if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s IS NULL',
            $grammar->wrap($index),
            $grammar->wrapTable($table),
            implode(', ', array_map(
                fn (string $column): string => $grammar->wrap($column),
                [...$scope, 'name'],
            )),
            $grammar->wrap('deleted_at'),
        ));

        return;
    }

    Schema::table($table, static function (Blueprint $blueprint) use ($scope, $index): void {
        $blueprint->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
        $blueprint->unique([...$scope, 'active_name'], $index);
    });
}
