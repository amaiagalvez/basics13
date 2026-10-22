<?php

namespace Basics13\Tests\Fixtures;

class TestListQueryWithFilters extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date', 'visible' => true, 'order' => 10, 'group' => 'dates'],
            'active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean', 'visible' => true, 'order' => 10, 'group' => 'status'],
            'priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number', 'visible' => true, 'order' => 10, 'group' => 'numbers'],
        ];
    }
}
