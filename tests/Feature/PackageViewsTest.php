<?php

namespace Basics13\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Blade;
use Basics13\Livewire\AdvancedSearchModal;
use Illuminate\Pagination\LengthAwarePaginator;

final class PackageViewsTest extends FeatureTestCase
{
    public function test_package_views_are_loaded(): void
    {
        $providers = array_keys(App::getLoadedProviders());
        $this->assertContains('Basics13\ServiceProvider', $providers, 'Basics13 ServiceProvider not loaded');

        $this->assertTrue(View::exists('basics13::components.list.create-action'));
        $this->assertTrue(View::exists('basics13::components.forms.field-label'));
    }

    public function test_package_view_can_be_rendered(): void
    {
        $html = view('basics13::components.list.create-action', [
            'prefix' => 'test',
            'label' => 'Test Label',
            'click' => 'testClick()',
        ])->render();

        $this->assertStringContainsString('Test Label', $html);
        $this->assertStringContainsString('testClick()', $html);
    }

    public function test_list_header_renders_its_actions_and_the_state_tabs(): void
    {
        $html = Blade::render(
            <<<'BLADE'
            <x-basics13::list.page-header :list="$list" prefix="entity">
                <x-slot:actions>
                    <button type="button">Create resource</button>
                </x-slot:actions>
            </x-basics13::list.page-header>
            BLADE,
            ['list' => $this->listPayload()],
        );

        $this->assertStringContainsString('Create resource', $html);
        $this->assertStringContainsString('data-test="entity-active"', $html);
        $this->assertStringNotContainsString('data-test="entity-advanced-search"', $html);
    }

    public function test_list_toolbar_renders_the_search_the_advanced_search_and_the_active_filters(): void
    {
        // Flux inputs read the shared error bag a request would provide.
        View::share('errors', new ViewErrorBag);

        $html = Blade::render(
            <<<'BLADE'
            <x-basics13::list.search :search="$search" prefix="entity" :searchableFields="$searchableFields" :filters="$filters" />
            BLADE,
            [
                'search' => ['action' => '/entities', 'placeholder' => 'Search', 'value' => ''],
                'searchableFields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'visible' => true],
                    'notes' => ['label' => 'Notes', 'type' => 'text', 'visible' => true],
                ],
                'filters' => [
                    'name' => ['operator' => 'contains', 'value' => '001'],
                    'notes' => ['operator' => 'starts_with', 'value' => 'Draft'],
                ],
            ],
        );

        $this->assertStringContainsString('data-test="entity-search"', $html);
        $this->assertStringContainsString('data-test="entity-advanced-search"', $html);
        $this->assertMatchesRegularExpression('/data-test="entity-advanced-search-count">\s*2\s*</', $html);
        $this->assertStringContainsString('data-test="entity-filter-badges"', $html);
        // The default operator goes without saying; any other one is spelled out.
        $this->assertStringNotContainsString(__('basics13::messages.operator_contains'), $html);
        $this->assertStringContainsString(__('basics13::messages.operator_starts_with'), $html);
    }

    public function test_list_toolbar_without_filterable_fields_renders_only_the_search(): void
    {
        // Flux inputs read the shared error bag a request would provide.
        View::share('errors', new ViewErrorBag);

        $html = Blade::render(
            '<x-basics13::list.search :search="$search" prefix="entity" />',
            ['search' => ['action' => '/entities', 'placeholder' => 'Search', 'value' => '']],
        );

        $this->assertStringContainsString('data-test="entity-search"', $html);
        $this->assertStringNotContainsString('data-test="entity-advanced-search"', $html);
        $this->assertStringNotContainsString('data-test="entity-filter-badges"', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(): array
    {
        return [
            'resource' => 'Entities',
            'breadcrumbs' => [
                ['label' => 'Entities', 'url' => '/entities'],
            ],
            'tabs' => [
                [
                    'current' => true,
                    'url' => '/entities',
                    'test' => 'entity-active',
                    'label' => 'Active',
                    'count' => 1,
                ],
            ],
        ];
    }

    public function test_advanced_search_resolves_scope_dependent_field_callbacks_before_livewire_persistence(): void
    {
        Livewire::test(AdvancedSearchModal::class, [
            'fields' => [
                'entity_type_ids' => [
                    'label' => 'Entity types',
                    'type' => 'select',
                    'visible' => true,
                    'condition' => fn (string $scope): bool => $scope === 'archived',
                    'options' => fn (string $scope): array => [$scope => ucfirst($scope).' entity type'],
                ],
            ],
            'scope' => 'archived',
        ])->assertSee('Archived entity type');
    }

    public function test_host_view_overrides_take_precedence_over_package_views(): void
    {
        /** @var view-string $viewName */
        $viewName = 'basics13::components.list.create-action';
        $overrideDirectory = resource_path('views/vendor/basics13/components/list');
        $overridePath = $overrideDirectory.'/create-action.blade.php';
        File::ensureDirectoryExists($overrideDirectory);
        File::put($overridePath, 'Host override');
        View::prependNamespace('basics13', resource_path('views/vendor/basics13'));

        try {
            $this->assertSame(
                'Host override',
                trim(View::make($viewName)->render()),
            );
        } finally {
            File::delete($overridePath);
        }
    }

    public function test_package_validation_translations_are_available_to_the_validator(): void
    {
        App::setLocale('eu');

        $attributes = __('validation.attributes');

        $this->assertIsArray($attributes);
        $this->assertSame(__('basics13::validation.attributes.name'), $attributes['name']);
    }

    public function test_http_status_translations_are_available_in_every_supported_locale(): void
    {
        $expected = [
            'en' => 'Not Found',
            'eu' => 'Ez da aurkitu',
            'es' => 'No encontrado',
            'fr' => 'Page non trouvée',
        ];

        foreach ($expected as $locale => $notFound) {
            App::setLocale($locale);

            $this->assertSame($notFound, __('http-statuses.404'));
        }
    }

    public function test_row_actions_keep_two_as_buttons_and_group_three_or_more_in_a_menu(): void
    {
        $edit = ['type' => 'form-modal', 'label' => 'Edit', 'icon' => 'pencil-square', 'test' => 'item-edit-4', 'payload' => ['id' => 4]];
        $trash = ['type' => 'confirm-modal', 'label' => 'Trash', 'icon' => 'trash', 'test' => 'item-delete-4'];
        $sendTest = ['type' => 'modal', 'modal' => 'item-send-test', 'handler' => 'openSendTest', 'label' => 'Send test', 'icon' => 'paper-airplane', 'test' => 'item-send-test-4', 'payload' => ['action' => '/x']];
        $render = static fn (array $actions): string => Blade::render(
            '<x-basics13::list.row-actions prefix="item" edit-handler="editItem" :actions="$actions" />',
            ['actions' => $actions],
        );

        $this->assertStringNotContainsString('data-test="item-more-4"', $render([$edit, $trash]));

        $grouped = $render([$edit, $trash, $sendTest]);
        $this->assertStringContainsString('data-test="item-more-4"', $grouped);
        $this->assertStringContainsString('data-flux-menu-item', $grouped);
        $this->assertStringContainsString("name: 'item-send-test'", $grouped);
        $this->assertStringContainsString('openSendTest(JSON.parse($el.dataset.payload))', $grouped);
        $this->assertStringContainsString('data-test="item-delete-4"', $grouped);
    }

    public function test_package_table_keeps_the_pagination_summary_for_an_empty_list(): void
    {
        $html = Blade::render(
            '<x-basics13::list.table prefix="entity" :paginator="$paginator" />',
            ['paginator' => new LengthAwarePaginator([], 0, 15)],
        );

        $this->assertStringContainsString(
            __('basics13::messages.pagination_summary', ['first' => 0, 'last' => 0, 'total' => 0]),
            $html,
        );
    }

    public function test_the_excel_export_button_carries_the_search_filters_and_scope(): void
    {
        $this->get('/dashboard?search=ald&scope=archived&filters[name][operator]=contains&filters[name][value]=ald');

        $html = Blade::render('<x-basics13::list.excel-export prefix="entity" route="/entities/export" />');

        $this->assertStringContainsString('action="/entities/export"', $html);
        $this->assertStringContainsString('name="search" value="ald"', $html);
        $this->assertStringContainsString('name="scope" value="archived"', $html);
        $this->assertStringContainsString('name="filters[name][operator]" value="contains"', $html);
        $this->assertStringContainsString('name="filters[name][value]" value="ald"', $html);
        $this->assertStringContainsString('data-test="entity-excel-export"', $html);
        $this->assertStringContainsString(__('basics13::messages.export_to_excel'), $html);
    }

    public function test_the_excel_export_button_is_not_rendered_when_disabled(): void
    {
        $html = Blade::render('<x-basics13::list.excel-export prefix="entity" :enabled="false" />');

        $this->assertSame('', trim($html));
    }

    public function test_the_excel_export_button_takes_the_scope_the_listing_gives_it(): void
    {
        $html = Blade::render('<x-basics13::list.excel-export prefix="project" route="/projects/export" scope="archived" />');

        $this->assertStringContainsString('name="scope" value="archived"', $html);
    }

    public function test_the_excel_export_button_submits_filter_values_as_lists(): void
    {
        $this->app->instance('request', Request::create('/entities', 'GET', [
            'filters' => [
                'entity_type_ids' => ['operator' => 'equals', 'value' => ['1', '2']],
                'name' => ['operator' => 'contains', 'value' => 'ald'],
            ],
        ]));

        $html = Blade::render('<x-basics13::list.excel-export prefix="entity" route="/entities/export" />');

        $this->assertStringContainsString('name="filters[entity_type_ids][value][0]" value="1"', $html);
        $this->assertStringContainsString('name="filters[entity_type_ids][value][1]" value="2"', $html);
        $this->assertStringContainsString('name="filters[name][value]" value="ald"', $html);
    }
}
