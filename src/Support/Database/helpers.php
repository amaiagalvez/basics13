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
    addUniqueActiveColumnIndex($table, 'name', $scope);
}

/**
 * Make one column unique among the rows that are not soft-deleted.
 *
 * The same guarantee as addUniqueActiveNameIndex(), for a table that keeps a value to be unique in
 * a column that is not called `name`, such as one storing the value once per locale.
 *
 * @param  string  $column  The column whose value has to stay unique
 * @param  list<string>  $scope  Extra columns the value is unique within, besides $column itself
 */
function addUniqueActiveColumnIndex(string $table, string $column, array $scope = []): void
{
    $index = implode('_', array_merge([$table], $scope, ['active_'.$column, 'unique']));

    if (isSqliteOrPgsql()) {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s IS NULL',
            $grammar->wrap($index),
            $grammar->wrapTable($table),
            implode(', ', array_map(
                fn (string $name): string => $grammar->wrap($name),
                [...$scope, $column],
            )),
            $grammar->wrap('deleted_at'),
        ));

        return;
    }

    $generated = 'active_'.$column;

    Schema::table($table, static function (Blueprint $blueprint) use ($scope, $generated, $column, $index): void {
        $blueprint->string($generated)->nullable()->storedAs('IF(deleted_at IS NULL, '.$column.', NULL)');
        $blueprint->unique([...$scope, $generated], $index);
    });
}
