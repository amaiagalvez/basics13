<?php

namespace Basics13\Tests;

use LogicException;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * Unit tests run without a database: a database trait or a query there fails the test,
 * so the test is moved to tests/Feature or rewritten without the database.
 *
 * @phpstan-require-extends TestbenchTestCase
 */
trait ForbidsDatabaseInUnitTests
{
    protected function forbidDatabaseInUnitTests(): void
    {
        if (! str_contains(static::class, '\\Tests\\Unit\\')) {
            return;
        }

        if (static::usesDatabase()) {
            throw new LogicException(static::class.' uses a database trait: move it to tests/Feature.');
        }

        DB::beforeExecuting(static function (string $query): never {
            throw new LogicException("Unit tests must not query the database, move the test to tests/Feature: {$query}");
        });
    }

    protected static function usesDatabase(): bool
    {
        return static::usesRefreshDatabaseTestingConcern() || static::usesTestingConcern(DatabaseMigrations::class);
    }
}
