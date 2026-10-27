<?php

namespace Basics13\Tests;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Illuminate\Support\Facades\Validator;
use Basics13\Concerns\TransactionalDelete;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Basics13\Http\Requests\SearchableListRequest;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * The canonical resource pattern, checked the same way in every package: each resource ships the
 * complete set, controllers stay thin, and every input endpoint goes through a Form Request. A
 * trait rather than a base class because the route checks need the package's own application.
 *
 * @phpstan-require-extends TestCase
 */
trait ChecksResourceArchitecture
{
    abstract protected function packageRoot(): string;

    /**
     * Resource name (singular, StudlyCase) => model class.
     *
     * @return array<string, class-string>
     */
    abstract protected function resources(): array;

    public function test_every_resource_ships_the_complete_set(): void
    {
        foreach ($this->resourcesOrSkip() as $resource => $modelClass) {
            $namespace = Str::before($modelClass, '\\Models\\');
            $plural = Str::pluralStudly($resource);

            $classes = [
                "{$namespace}\\Http\\Controllers\\{$resource}Controller",
                "{$namespace}\\Http\\Controllers\\{$resource}TrashController",
                "{$namespace}\\Http\\Controllers\\{$resource}ArchivedController",
                "{$namespace}\\Http\\Requests\\{$resource}Request",
                "{$namespace}\\Http\\Requests\\{$resource}ListRequest",
                "{$namespace}\\Http\\Requests\\{$resource}RestoreRequest",
                "{$namespace}\\Http\\Requests\\{$resource}DestroyRequest",
                "{$namespace}\\Policies\\{$resource}Policy",
                "{$namespace}\\Queries\\{$plural}\\{$resource}ListQuery",
                "{$namespace}\\Transformers\\{$resource}ListTransformer",
                "{$namespace}\\Database\\Factories\\{$resource}Factory",
            ];

            foreach ($classes as $class) {
                $this->assertTrue(class_exists($class), "Missing class {$class}");
            }

            $this->assertNotEmpty(
                (new ReflectionClass($modelClass))->getAttributes(UsePolicy::class),
                "{$resource} must declare #[UsePolicy]",
            );

            foreach (['list', 'form'] as $view) {
                $this->assertFileExists($this->viewPath($resource, $view));
            }
        }
    }

    public function test_resource_view_abilities_match_show_routes(): void
    {
        foreach ($this->resourcesOrSkip() as $resource => $modelClass) {
            $namespace = Str::before($modelClass, '\\Models\\');
            $policy = "{$namespace}\\Policies\\{$resource}Policy";

            if (! class_exists($policy)) {
                $this->fail("Missing class {$policy}");
            }

            $this->assertSame(
                Route::has($this->routePrefix($resource).'.show'),
                (new ReflectionClass($policy))->hasMethod('view'),
                "{$resource}Policy::view must exist exactly when its show route exists",
            );
        }
    }

    public function test_views_follow_the_canonical_patterns(): void
    {
        foreach (array_keys($this->resourcesOrSkip()) as $resource) {
            $this->assertStringContainsString(
                '<x-basics13::list.table',
                (string) file_get_contents($this->viewPath($resource, 'list')),
                "{$resource} list must use <x-basics13::list.table> instead of its own table markup",
            );
            $this->assertStringContainsString(
                '<x-basics13::forms.tracked-resource',
                (string) file_get_contents($this->viewPath($resource, 'form')),
                "{$resource} form must use <x-basics13::forms.tracked-resource>",
            );
        }
    }

    public function test_controllers_stay_thin_and_free_of_raw_input(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/DB::(?!transaction\s*\()/',
                $source,
                "{$file} may use DB::transaction() but no other DB facade calls",
            );
            $this->assertStringNotContainsString('Schema::', $source, "{$file} must not touch Schema::");
            $this->assertDoesNotMatchRegularExpression('/\b(?:dd|dump)\s*\(/', $source, "{$file} contains dd/dump");
            $this->assertStringNotContainsString(
                '->paginate(',
                $source,
                "{$file} must paginate in app/Queries, not in the controller",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$request->(?:input|all|only|except)\s*\(/',
                $source,
                "{$file} reads raw input; use a FormRequest and validated data",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(?<!\w)Request\s+\$request\b/',
                $source,
                "{$file} type-hints the base Request; every input endpoint needs a FormRequest",
            );
        }
    }

    public function test_input_endpoints_declare_a_form_request(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $reflection = new ReflectionClass($this->classIn($file));

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }

                if (! in_array($method->getName(), ['index', 'store', 'update', 'restore'], true)) {
                    continue;
                }

                $declaresFormRequest = array_filter(
                    $method->getParameters(),
                    fn ($parameter): bool => $parameter->hasType()
                        && is_a((string) $parameter->getType(), FormRequest::class, true),
                );

                $this->assertNotEmpty(
                    $declaresFormRequest,
                    "{$reflection->getName()}::{$method->getName()} must declare a FormRequest (user input must be validated)",
                );
            }
        }
    }

    public function test_form_requests_expose_rules_and_authorize(): void
    {
        foreach (glob($this->packageRoot().'/src/Http/Requests/*Request.php') ?: [] as $file) {
            $reflection = new ReflectionClass($this->classIn($file));

            $this->assertTrue(
                $reflection->isSubclassOf(FormRequest::class),
                "{$reflection->getName()} must extend FormRequest",
            );

            if ($reflection->isAbstract()) {
                continue;
            }

            foreach (['rules', 'authorize'] as $method) {
                $this->assertTrue(
                    $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic(),
                    "{$reflection->getName()} must define public {$method}()",
                );
            }
        }
    }

    public function test_resource_models_share_the_lifecycle_traits_and_factory_states(): void
    {
        foreach ($this->resourcesOrSkip() as $resource => $modelClass) {
            $traits = class_uses_recursive($modelClass);

            foreach ([SoftDeletes::class, TracksAuditColumns::class, TransactionalDelete::class] as $trait) {
                $this->assertContains($trait, $traits, "{$resource} must use {$trait}");
            }

            $model = new $modelClass;
            $this->assertInstanceOf(Model::class, $model);
            $factory = $modelClass::factory();
            $this->assertTrue(method_exists($factory, 'trashed'), "{$resource} factory must offer the trashed() state");

            if (in_array('active', $model->getFillable(), true)) {
                $this->assertArrayHasKey('active', $model->getCasts(), "{$resource} must cast its active flag");
                $this->assertTrue(method_exists($factory, 'archived'), "{$resource} factory must offer the archived() state");
            }
        }
    }

    public function test_list_requests_share_the_search_rules_and_normalization(): void
    {
        $limit = MaxLength::string();

        foreach ($this->resourcesOrSkip() as $resource => $modelClass) {
            $requestClass = Str::before($modelClass, '\\Models\\')."\\Http\\Requests\\{$resource}ListRequest";
            $this->assertTrue(is_a($requestClass, SearchableListRequest::class, true), "{$requestClass} must extend SearchableListRequest");

            $rules = $this->listRequest($requestClass, null)->rules();
            $this->assertTrue(Validator::make([], $rules)->passes(), "{$resource}: an omitted search must pass");
            $this->assertTrue(Validator::make(['search' => str_repeat('a', $limit)], $rules)->passes(), "{$resource}: a search at the limit must pass");
            $this->assertTrue(Validator::make(['search' => ['Ane']], $rules)->fails(), "{$resource}: an array search must fail");
            $this->assertTrue(Validator::make(['search' => str_repeat('a', $limit + 1)], $rules)->fails(), "{$resource}: an over-long search must fail");

            foreach ([[null, ''], ['', ''], ['  x  ', 'x'], ['   ', '']] as [$input, $expected]) {
                $this->assertSame($expected, $this->listRequest($requestClass, $input)->search(), "{$resource}: search normalization");
            }
        }
    }

    /**
     * @return array<string, class-string>
     */
    private function resourcesOrSkip(): array
    {
        if ($this->resources() === []) {
            $this->markTestSkipped('The package ships no resources.');
        }

        return $this->resources();
    }

    /**
     * @param  class-string<SearchableListRequest>  $requestClass
     */
    private function listRequest(string $requestClass, mixed $search): SearchableListRequest
    {
        $request = $requestClass::create('/', 'GET', $search === null ? [] : ['search' => $search]);
        $request->setRouteResolver(static fn (): null => null);

        return $request;
    }

    private function routePrefix(string $resource): string
    {
        return Str::kebab(Str::pluralStudly($resource));
    }

    private function viewPath(string $resource, string $view): string
    {
        return $this->packageRoot()."/resources/views/{$this->routePrefix($resource)}/{$view}.blade.php";
    }

    /**
     * @return list<string>
     */
    private function controllerFiles(): array
    {
        return array_values(array_filter(
            glob($this->packageRoot().'/src/Http/Controllers/*.php') ?: [],
            fn (string $file): bool => basename($file) !== 'Controller.php',
        ));
    }

    /**
     * @return class-string
     */
    private function classIn(string $file): string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents($file), $namespace) !== 1) {
            $this->fail("Missing namespace declaration in {$file}");
        }

        $class = $namespace[1].'\\'.basename($file, '.php');

        if (! class_exists($class)) {
            $this->fail("Missing class {$class}");
        }

        return $class;
    }
}
