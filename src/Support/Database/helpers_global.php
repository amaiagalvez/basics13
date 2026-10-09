<?php

use Basics13\Support\Database\Helpers;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;

/**
 * Check if the database driver is SQLite or PostgreSQL.
 */
function isSqliteOrPgsql(): bool
{
    return Helpers::isSqliteOrPgsql();
}

/**
 * Add the columns shared by the application's soft-deleting tables.
 */
function addCommonColumns(BaseBlueprint $table): void
{
    Helpers::addCommonColumns($table);
}

/**
 * Add the three audit columns that record who created, last changed and trashed a row.
 */
function addAuditColumns(BaseBlueprint $table): void
{
    Helpers::addAuditColumns($table);
}

/**
 * Make a table's name unique among the rows that are not soft-deleted.
 *
 * @param  list<string>  $scope  Extra columns the name is unique within, besides [name] itself
 */
function addUniqueActiveNameIndex(string $table, array $scope = []): void
{
    Helpers::addUniqueActiveNameIndex($table, $scope);
}