<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Http\Request;
use Basics13\Support\Validation\MaxLength;
use Basics13\Http\Requests\SearchableSelectOptionsRequest;

class SearchableSelectOptionsRequestTest extends TestCase
{
    public function test_rules_returns_q_rule_with_max_length(): void
    {
        $request = new class extends SearchableSelectOptionsRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        };

        $rules = $request->rules();

        $this->assertArrayHasKey('q', $rules);
        $this->assertContains('nullable', $rules['q']);
        $this->assertContains('string', $rules['q']);
        $this->assertContains('max:'.MaxLength::string(), $rules['q']);
    }

    public function test_search_returns_trimmed_string(): void
    {
        $request = Request::create('/', 'GET', ['q' => '  trimmed  ']);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(fn () => null);

        $testRequest = new class extends SearchableSelectOptionsRequest
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

        $testRequest = new class extends SearchableSelectOptionsRequest
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
