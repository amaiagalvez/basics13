<?php

namespace Basics13\Tests\Fixtures;

class TestListQueryWithVisibleFlags extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'notes' => ['column' => 'notes', 'label' => 'test.notes', 'type' => 'text', 'visible' => false, 'order' => 20, 'group' => 'basic'],
        ];
    }
}
