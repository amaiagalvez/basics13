<?php

namespace Basics13\Tests\Fixtures;

use Basics13\Queries\ListQueryBase;

/**
 * Concrete implementation of ListQueryBase for testing.
 *
 * @extends ListQueryBase<AuditedRecord>
 */
class TestListQuery extends ListQueryBase
{
    /**
     * @return class-string<AuditedRecord>
     */
    protected function getModelClass(): string
    {
        return AuditedRecord::class;
    }

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

    public function searchableFields(string $scope): array
    {
        return [
            'name' => [
                'column' => 'name',
                'label' => 'test.name',
                'type' => 'text',
                'operators' => ['contains', 'equals'],
                'default_operator' => 'contains',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
        ];
    }
}
