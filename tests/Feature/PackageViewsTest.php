<?php

namespace Basics13\Tests\Feature;

use Basics13\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

final class PackageViewsTest extends FeatureTestCase
{
    public function test_package_views_are_loaded(): void
    {
        $providers = array_keys($this->app->getLoadedProviders());
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

    public function test_package_validation_translations_are_available_to_the_validator(): void
    {
        $this->app->setLocale('eu');

        $attributes = __('validation.attributes');

        $this->assertIsArray($attributes);
        $this->assertSame(__('basics13::validation.attributes.name'), $attributes['name']);
    }

    public function test_package_table_keeps_the_pagination_summary_for_an_empty_list(): void
    {
        $html = Blade::render(
            '<x-basics13::list.table prefix="customer" :paginator="$paginator" />',
            ['paginator' => new LengthAwarePaginator([], 0, 15)],
        );

        $this->assertStringContainsString(__('Showing'), $html);
        $this->assertStringContainsString(__('results'), $html);
    }
}