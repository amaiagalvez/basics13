<?php

namespace Basics13\Tests\Unit\Support\Testing;

use Basics13\Tests\TestCase;
use Illuminate\Routing\Route;
use Illuminate\Foundation\Http\FormRequest;
use Basics13\Tests\Fixtures\BoundedLengthRequest;
use Basics13\Support\Testing\ValidatesFormRequests;

final class ValidatesFormRequestsTest extends TestCase
{
    use ValidatesFormRequests;

    public function test_it_passes_when_the_payload_satisfies_the_rules(): void
    {
        $request = BoundedLengthRequest::create('/', 'POST', [
            'code' => 'ab',
            'nickname' => 'ab',
            'slug' => 'valid-slug',
        ]);

        $this->assertRequestValidationPasses($request, static fn (): array => $request->rules());
    }

    public function test_it_fails_on_the_field_that_broke_a_rule(): void
    {
        $request = BoundedLengthRequest::create('/', 'POST', [
            'code' => 'ab',
            'nickname' => 'ab',
            'slug' => 'ab',
        ]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'slug');
    }

    public function test_it_binds_a_route_so_rules_can_read_it(): void
    {
        $request = new class extends FormRequest
        {
            public mixed $routeSeenByRules = null;

            /**
             * @return array<string, array<int, mixed>>
             */
            public function rules(): array
            {
                $this->routeSeenByRules = $this->route();

                return ['code' => ['string', 'min:2']];
            }
        };

        $this->assertRequestValidationPasses($request, static fn (): array => $request->rules());

        $this->assertInstanceOf(Route::class, $request->routeSeenByRules);
    }
}
