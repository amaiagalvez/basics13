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
}
