<?php

namespace Basics13\Tests\Fixtures;

use Basics13\Queries\ListQueryBase;

/** @extends ListQueryBase<AuditedRecord> */
abstract class TestQueryWithAdvancedFilters extends ListQueryBase
{
    protected function integration(): object
    {
        return new class
        {
            /**
             * @param  non-empty-list<literal-string>  $columns
             * @return non-empty-list<literal-string>
             */
            public function searchColumns(string $scope, array $columns): array
            {
                return $columns;
            }
        };
    }

    /**
     * @return class-string<AuditedRecord>
     */
    protected function getModelClass(): string
    {
        return AuditedRecord::class;
    }
}
