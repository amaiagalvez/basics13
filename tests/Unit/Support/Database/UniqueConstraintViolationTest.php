<?php

namespace Basics13\Tests\Unit\Support\Database;

use PDOException;
use Basics13\Tests\TestCase;
use Illuminate\Translation\Translator;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Basics13\Support\Database\UniqueConstraintViolation;

class UniqueConstraintViolationTest extends TestCase
{
    public function test_detects_mysql_duplicate_key_violations(): void
    {
        $exception = $this->queryException(['23000', 1062, 'Duplicate entry']);

        $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
    }

    public function test_normalizes_numeric_sqlstate_for_mysql_duplicate_key_violations(): void
    {
        $exception = $this->queryException([23000, 1062, 'Duplicate entry']);

        $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
    }

    public function test_detects_sqlite_duplicate_key_violations(): void
    {
        $exception = $this->queryException(['23000', 19, 'UNIQUE constraint failed']);

        $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
    }

    public function test_detects_postgresql_duplicate_key_violations(): void
    {
        $exception = $this->queryException(['23505', null, 'duplicate key value']);

        $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
    }

    public function test_does_not_detect_other_integrity_violations_as_duplicate_keys(): void
    {
        $exception = $this->queryException(['23000', 1452, 'Foreign key constraint fails']);

        $this->assertFalse(UniqueConstraintViolation::causedBy($exception));
    }

    public function test_rethrow_as_validation_error_throws_validation_exception_for_duplicate_key(): void
    {
        $exception = $this->queryException(['23000', 1062, 'Duplicate entry']);

        // Ensure translator is available for __() helper
        if ($this->app !== null && ! $this->app->bound('translator')) {
            $loader = $this->app->make('translation.loader');
            $locale = $this->app->getLocale();
            $this->app->instance('translator', new Translator($loader, $locale));
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already been taken');

        UniqueConstraintViolation::rethrowAsValidationError($exception, 'name');
    }

    public function test_rethrow_as_validation_error_throws_original_exception_for_non_duplicate_key(): void
    {
        $exception = $this->queryException(['23000', 1452, 'Foreign key constraint fails']);

        $this->expectException(QueryException::class);

        UniqueConstraintViolation::rethrowAsValidationError($exception);
    }

    /**
     * @param  array{0: string|int, 1: int|null, 2: string}  $errorInfo
     */
    private function queryException(array $errorInfo): QueryException
    {
        $previous = new PDOException($errorInfo[2]);
        $previous->errorInfo = $errorInfo;

        return new QueryException('mysql', 'insert into records', [], $previous);
    }
}
