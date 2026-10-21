<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Http\Request;
use Basics13\Support\Validation\MaxLength;
use Basics13\Http\Requests\SearchableListRequest;

class SearchableListRequestTest extends TestCase
{
    public function test_rules_returns_search_rule_with_max_length(): void
    {
        $request = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };

        $rules = $request->rules();

        $this->assertArrayHasKey('search', $rules);
        $this->assertContains('nullable', $rules['search']);
        $this->assertContains('string', $rules['search']);
        $this->assertContains('max:'.MaxLength::string(), $rules['search']);
    }

    public function test_search_returns_trimmed_string(): void
    {
        $request = Request::create('/', 'GET', ['search' => '  trimmed  ']);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $this->assertSame('trimmed', $testRequest->search());
    }

    public function test_search_returns_empty_string_when_not_provided(): void
    {
        $request = Request::create('/', 'GET', []);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $this->assertSame('', $testRequest->search());
    }

    public function test_filters_returns_null_when_not_provided(): void
    {
        $request = Request::create('/', 'GET', []);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $this->assertNull($testRequest->filters());
    }

    public function test_filters_returns_null_when_not_array(): void
    {
        $request = Request::create('/', 'GET', ['filters' => 'not-an-array']);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $this->assertNull($testRequest->filters());
    }

    public function test_filters_returns_normalized_filters(): void
    {
        $request = Request::create('/', 'GET', [
            'filters' => [
                'name' => ['operator' => 'contains', 'value' => 'test'],
                'email' => ['operator' => 'equals', 'value' => 'user@example.com'],
            ],
        ]);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $filters = $testRequest->filters();

        $this->assertIsArray($filters);
        $this->assertArrayHasKey('name', $filters);
        $this->assertSame('contains', $filters['name']['operator']);
        $this->assertSame('test', $filters['name']['value']);
        $this->assertArrayHasKey('email', $filters);
        $this->assertSame('equals', $filters['email']['operator']);
        $this->assertSame('user@example.com', $filters['email']['value']);
    }

    public function test_filters_skips_invalid_filter_entries(): void
    {
        $request = Request::create('/', 'GET', [
            'filters' => [
                'name' => ['operator' => 'contains', 'value' => 'test'],
                'invalid' => 'not-an-array',
                'empty_operator' => ['value' => 'test'],
            ],
        ]);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $filters = $testRequest->filters();

        $this->assertIsArray($filters);
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayNotHasKey('invalid', $filters);
        $this->assertArrayNotHasKey('empty_operator', $filters);
    }

    public function test_filters_drop_values_that_are_not_a_scalar_or_a_flat_list(): void
    {
        $request = Request::create('/', 'GET', [
            'filters' => [
                'name' => ['operator' => 'contains', 'value' => 'test'],
                'entity_id' => ['operator' => 'equals', 'value' => ['1', '3']],
                'nested' => ['operator' => 'equals', 'value' => [['1']]],
                'keyed' => ['operator' => 'between', 'value' => ['a' => '2026-01-01', 'b' => '2026-02-01']],
                'array_operator' => ['operator' => ['contains'], 'value' => 'test'],
            ],
        ]);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $this->assertSame(['name', 'entity_id'], array_keys($testRequest->filters() ?? []));
    }

    public function test_filters_returns_null_when_all_invalid(): void
    {
        $request = Request::create('/', 'GET', [
            'filters' => [
                'invalid' => 'not-an-array',
                'empty_operator' => ['value' => 'test'],
            ],
        ]);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableListRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };
        $testRequest->setRouteResolver(fn () => null);
        $testRequest->merge($request->all());

        $this->assertNull($testRequest->filters());
    }
}
