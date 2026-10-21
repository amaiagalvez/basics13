<?php

namespace Basics13\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
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

    public function test_list_header_renders_create_and_advanced_search_actions_together(): void
    {
        $html = Blade::render(
            <<<'BLADE'
            <x-basics13::list.page-header
                :list="$list"
                prefix="entity"
                :searchableFields="$searchableFields"
            >
                <x-slot:actions>
                    <button type="button">Create resource</button>
                </x-slot:actions>
            </x-basics13::list.page-header>
            BLADE,
            [
                'list' => [
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
                ],
                'searchableFields' => [
                    'name' => [
                        'label' => 'Name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                ],
            ],
        );

        $this->assertStringContainsString('Create resource', $html);
        $this->assertStringContainsString('data-test="entity-advanced-search"', $html);
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
}
