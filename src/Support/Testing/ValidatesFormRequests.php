<?php

namespace Basics13\Support\Testing;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as LaravelValidator;

/**
 * Assertions for Form Request rules, shared by every package's unit tests.
 *
 * A Form Request's `rules()` is a plain method, so its rules can be run against any payload without
 * booting a request or a database. The request is bound to a route first because rules such as
 * `Rule::unique()->ignore($this->route('record'))` read the route binding.
 *
 * This trait lives in src/ rather than in a package's tests/ because Composer does not autoload a
 * dependency's `autoload-dev` namespaces: `Basics13\Tests\` resolves only inside basics13 itself, so
 * a trait placed there would be "class not found" for the packages that build on basics13.
 */
trait ValidatesFormRequests
{
    /**
     * @param  Closure(): array<string, array<int, mixed>>  $rules
     */
    protected function assertRequestValidationFails(FormRequest $request, Closure $rules, string $field): void
    {
        $validator = $this->validatorForRequest($request, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @param  Closure(): array<string, array<int, mixed>>  $rules
     */
    protected function assertRequestValidationPasses(FormRequest $request, Closure $rules): void
    {
        $validator = $this->validatorForRequest($request, $rules);

        $this->assertFalse($validator->fails());
    }

    /**
     * @param  Closure(): array<string, array<int, mixed>>  $rules
     */
    protected function validatorForRequest(FormRequest $request, Closure $rules): LaravelValidator
    {
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        return Validator::make($request->all(), $rules());
    }
}
