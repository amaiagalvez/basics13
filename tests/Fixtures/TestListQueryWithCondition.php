<?php

namespace Basics13\Tests\Fixtures;

class TestListQueryWithCondition extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'condition' => fn (string $scope) => $scope !== 'active',
            ],
        ];
    }
}
