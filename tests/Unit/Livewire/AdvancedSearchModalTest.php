<?php

namespace Basics13\Tests\Unit\Livewire;

use Livewire\Livewire;
use Basics13\Livewire\AdvancedSearchModal;
use Basics13\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

class AdvancedSearchModalTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure translation files are loaded
        $this->loadLaravelTranslations();
    }

    private function loadLaravelTranslations(): void
    {
        $loader = app('translation.loader');
        $loader->addNamespace('basics13', __DIR__.'/../../../resources/lang');
    }

    public function test_component_mounts_with_defaults(): void
    {
        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => [], 'filters' => [], 'scope' => 'active']);

        $this->assertSame([], $component->get('fields'));
        $this->assertSame([], $component->get('filters'));
        $this->assertSame('active', $component->get('scope'));
        $this->assertFalse($component->get('show'));
        $this->assertFalse($component->get('showAdvanced'));
    }

    public function test_component_mounts_with_custom_fields(): void
    {
        $fields = [
            'name' => [
                'column' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);

        $fieldsProp = $component->get('fields');
        $this->assertArrayHasKey('name', $fieldsProp);
        $this->assertEquals('contains', $fieldsProp['name']['default_operator']);
        $this->assertEquals(['contains', 'equals', 'not_equals', 'starts_with', 'ends_with'], $fieldsProp['name']['operators']);
    }

    public function test_value_inputs_are_named_after_their_field_and_the_toggle_reports_its_state(): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text'],
            'start_date' => ['column' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
            'notes' => ['column' => 'notes', 'label' => 'Notes', 'type' => 'text', 'visible' => false],
        ];

        Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active'])
            ->assertSeeHtml('aria-label="Name"')
            ->assertSeeHtml('aria-label="Start date – '.e(__('basics13::messages.date_from')).'"')
            ->assertSeeHtml('aria-label="Start date – '.e(__('basics13::messages.date_to')).'"')
            ->assertSeeHtml('aria-expanded="false"')
            ->call('toggleAdvanced')
            ->assertSeeHtml('aria-expanded="true"');
    }

    public function test_toggle_advanced_toggles_show_advanced(): void
    {
        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => [], 'filters' => [], 'scope' => 'active']);

        $this->assertFalse($component->get('showAdvanced'));

        $component->call('toggleAdvanced');
        $this->assertTrue($component->get('showAdvanced'));

        $component->call('toggleAdvanced');
        $this->assertFalse($component->get('showAdvanced'));
    }

    public function test_normalize_field_sets_default_operators(): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);

        $normalized = $component->get('fields')['name'];

        $this->assertArrayHasKey('operators', $normalized);
        $this->assertArrayHasKey('default_operator', $normalized);
    }

    public function test_a_field_whose_condition_fails_is_dropped_even_from_the_advanced_fields(): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => false],
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'Entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'condition' => fn (string $scope) => $scope === 'archived',
            ],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active'])
            ->call('toggleAdvanced');

        /** @var AdvancedSearchModal $modal */
        $modal = $component->instance();

        $this->assertArrayNotHasKey('entity_id', $component->get('fields'));
        $this->assertArrayNotHasKey('entity_id', $component->get('filters'));
        $this->assertSame(['name'], array_keys($modal->getVisibleFields()));
    }

    public function test_a_field_whose_condition_holds_keeps_its_visibility_without_the_callable(): void
    {
        $fields = [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'Entity',
                'type' => 'select',
                'condition' => fn (string $scope) => $scope === 'archived',
            ],
        ];

        $normalized = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'archived'])
            ->get('fields')['entity_id'];

        $this->assertArrayNotHasKey('condition', $normalized);
        $this->assertTrue($normalized['visible'] ?? true);
    }

    /**
     * The field definitions are resolved on the server once. Were the client able to rewrite them,
     * a string such as "system" in `condition` or `options` would be called as a PHP function with
     * the scope as its argument.
     *
     * @return array<string, array{string, mixed}>
     */
    public static function clientUpdates(): array
    {
        return [
            'field condition' => ['fields.name.condition', 'print_r'],
            'field options' => ['fields.name.options', 'print_r'],
            'whole fields' => ['fields', ['name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text', 'condition' => 'print_r']]],
            'scope' => ['scope', 'LW-INJECTION-MARKER'],
        ];
    }

    #[DataProvider('clientUpdates')]
    public function test_the_client_cannot_change_the_field_definitions_or_the_scope(string $property, mixed $value): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text'],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $component->set($property, $value);
    }

    public function test_changing_a_date_operator_starts_its_value_over(): void
    {
        $fields = [
            'start_date' => ['column' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text'],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active'])
            ->set('filters.start_date.value', ['2026-01-01', '2026-02-01'])
            ->set('filters.name.value', 'Alpha')
            ->set('filters.start_date.operator', 'before')
            ->set('filters.name.operator', 'equals');

        $this->assertNull($component->get('filters')['start_date']['value']);
        $this->assertSame('Alpha', $component->get('filters')['name']['value']);
    }

    public function test_normalize_field_calls_options_callable(): void
    {
        $fields = [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'Entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'options' => fn (string $scope) => ['active' => [1 => 'Entity One']][$scope] ?? [],
            ],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);

        $normalized = $component->get('fields')['entity_id'];

        $this->assertIsArray($normalized['options']);
        $this->assertSame([1 => 'Entity One'], $normalized['options']);
    }

    public function test_apply_filters_emits_events(): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);
        $component->set('filters', ['name' => ['operator' => 'contains', 'value' => 'test']]);

        $component->call('applyFilters')
            ->assertDispatched('filters-applied', filters: ['name' => ['operator' => 'contains', 'value' => 'test']])
            ->assertDispatched('modal-close', name: 'advanced-search');
    }

    public function test_clear_filters_resets_values_and_applies(): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'active' => ['column' => 'active', 'label' => 'Active', 'type' => 'boolean', 'visible' => true, 'order' => 10, 'group' => 'status'],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);
        $component->set('filters', [
            'name' => ['operator' => 'contains', 'value' => 'test'],
            'active' => ['operator' => 'is_true', 'value' => true],
        ]);

        $component->call('clearFilters');

        $filters = $component->get('filters');
        $this->assertNull($filters['name']['value']);
        $this->assertFalse($filters['active']['value']);
        $this->assertSame('', $filters['active']['operator']);
    }

    public function test_boolean_field_defaults_to_any_and_is_not_applied(): void
    {
        $fields = [
            'active' => ['column' => 'active', 'label' => 'Active', 'type' => 'boolean', 'visible' => true, 'order' => 10, 'group' => 'status'],
        ];

        $component = Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active']);

        $this->assertSame('', $component->get('filters')['active']['operator']);

        $component->call('applyFilters')
            ->assertDispatched('filters-applied', filters: []);
    }

    public function test_an_operator_without_translation_falls_back_to_a_readable_label(): void
    {
        $labels = (new AdvancedSearchModal)->getOperatorsForField([
            'column' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'operators' => ['contains', 'sounds_like'],
        ]);

        $this->assertSame(__('basics13::messages.operator_contains'), $labels['contains']);
        $this->assertSame('Sounds like', $labels['sounds_like']);
    }

    /**
     * Mount resolves the callable, so the view only ever lists an array; an unresolved callable
     * renders no options instead of being invoked from component state.
     */
    public function test_select_options_are_listed_only_when_already_resolved(): void
    {
        $modal = new AdvancedSearchModal;
        $field = ['column' => 'entity_id', 'label' => 'Entity', 'type' => 'select'];

        $this->assertSame([1 => 'One'], $modal->getSelectOptions([...$field, 'options' => [1 => 'One']]));
        $this->assertSame([], $modal->getSelectOptions([...$field, 'options' => fn (): array => [1 => 'One']]));
        $this->assertSame([], $modal->getSelectOptions($field));
    }

    public function test_apply_filters_drops_a_filter_for_an_unknown_field(): void
    {
        $fields = [
            'name' => ['column' => 'name', 'label' => 'Name', 'type' => 'text'],
        ];

        Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active'])
            ->set('filters', [
                'name' => ['operator' => 'contains', 'value' => 'Alpha'],
                'password' => ['operator' => 'equals', 'value' => 'secret'],
            ])
            ->call('applyFilters')
            ->assertDispatched('filters-applied', filters: ['name' => ['operator' => 'contains', 'value' => 'Alpha']]);
    }

    public function test_apply_filters_sends_a_single_multi_select_value_as_a_list(): void
    {
        $fields = [
            'project_id' => ['column' => 'project_id', 'label' => 'Project', 'type' => 'select', 'options' => [1 => 'One', 2 => 'Two']],
        ];

        Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active'])
            ->set('filters.project_id.value', '2')
            ->call('applyFilters')
            ->assertDispatched('filters-applied', filters: ['project_id' => ['operator' => 'equals', 'value' => ['2']]]);
    }

    public function test_apply_filters_sends_an_empty_list_for_a_multi_select_is_empty(): void
    {
        $fields = [
            'project_id' => ['column' => 'project_id', 'label' => 'Project', 'type' => 'select', 'nullable' => true, 'options' => [1 => 'One']],
        ];

        Livewire::test(AdvancedSearchModal::class, ['fields' => $fields, 'filters' => [], 'scope' => 'active'])
            ->set('filters.project_id', ['operator' => 'is_empty', 'value' => null])
            ->call('applyFilters')
            ->assertDispatched('filters-applied', filters: ['project_id' => ['operator' => 'is_empty', 'value' => []]]);
    }
}
