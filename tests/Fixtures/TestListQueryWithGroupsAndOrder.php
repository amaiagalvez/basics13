<?php

namespace Basics13\Tests\Fixtures;

class TestListQueryWithGroupsAndOrder extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'description' => ['column' => 'description', 'label' => 'test.description', 'type' => 'text', 'visible' => true, 'order' => 20, 'group' => 'basic'],
            'active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean', 'visible' => true, 'order' => 10, 'group' => 'status'],
            'created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date', 'visible' => true, 'order' => 10, 'group' => 'dates'],
        ];
    }
}
