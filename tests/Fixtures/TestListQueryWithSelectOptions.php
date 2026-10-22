<?php

namespace Basics13\Tests\Fixtures;

class TestListQueryWithSelectOptions extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'options' => fn (string $scope) => [1 => 'Entity One', 2 => 'Entity Two'],
                'multiple' => true,
            ],
        ];
    }
}
