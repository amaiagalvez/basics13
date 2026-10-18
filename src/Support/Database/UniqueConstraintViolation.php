<?php

namespace Basics13\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class UniqueConstraintViolation
{
    /**
     * Check if a query exception was caused by a unique constraint violation.
     *
     * Handles PostgreSQL (23505), MySQL/MariaDB (23000 + errno 19/1062), and SQLite (23000 + errno 19).
     */
    public static function causedBy(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());

        if ($sqlState === '23505') {
            return true;
        }

        return $sqlState === '23000'
            && in_array((int) ($errorInfo[1] ?? 0), [19, 1062], true);
    }

    /**
     * Extract the colliding column name from the exception message.
     *
     * Supports:
     * - MySQL/MariaDB: index names like `table_active_name_es_unique` or `table_name_es_unique`
     * - PostgreSQL: index names like `table_active_name_es_key` or `table_name_es_key`
     * - SQLite: bare column names like `table.name_es`
     *
     * @return string|null The column name (e.g., 'name_es') or null if not detectable
     */
    public static function getCollidingColumn(QueryException $exception): ?string
    {
        $message = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        // MySQL/MariaDB/PostgreSQL: look for index pattern *_active_<column>_unique|_key or *<column>_unique|_key
        if (preg_match('/[._](active_)?([a-z_]+)_(unique|key)/i', $message, $matches)) {
            $prefix = $matches[1] ?: '';
            $column = $matches[2];

            return $prefix.$column;
        }

        // SQLite: look for bare column reference like `table.column`
        if (preg_match('/`[^`]+\.([a-z_]+)`/i', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Report a unique index collision that passed validation (e.g. a concurrent write)
     * as a validation error on the detected field; rethrow any other database error.
     *
     * If the colliding column cannot be detected, falls back to the provided $fallbackField.
     *
     * @throws ValidationException
     * @throws QueryException
     */
    public static function rethrowAsValidationError(QueryException $exception, string $fallbackField = 'name'): never
    {
        if (! self::causedBy($exception)) {
            throw $exception;
        }

        $field = self::getCollidingColumn($exception) ?? $fallbackField;

        throw ValidationException::withMessages([
            $field => __('validation.unique', ['attribute' => __(ucfirst($field))]),
        ]);
    }
}
