<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Basics13\Tests\Fixtures\AuditActor;
use Basics13\Http\Requests\RestoreRequest;
use Basics13\Tests\Fixtures\AuditedRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RestoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_returns_resolve_name_conflict_rule(): void
    {
        $request = new class extends RestoreRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->findOrFail(1);
            }
        };

        $rules = $request->rules();

        $this->assertArrayHasKey('resolve_name_conflict', $rules);
        $this->assertContains('sometimes', $rules['resolve_name_conflict']);
        $this->assertContains('boolean', $rules['resolve_name_conflict']);
    }

    public function test_authorize_returns_true_when_user_can_restore(): void
    {
        Gate::define('restore', fn (AuditActor $user, AuditedRecord $record): bool => true);
        $user = AuditActor::factory()->create();
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = new class extends RestoreRequest
        {
            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->whereKey($this->route('record'))->firstOrFail();
            }
        };

        $route = new Route('PATCH', '/{record}', fn () => null);
        $route->bind($request);
        $route->setParameter('record', (string) $trashed->id);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_when_no_user(): void
    {
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = new class extends RestoreRequest
        {
            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->whereKey($this->route('record'))->firstOrFail();
            }
        };

        $route = new Route('PATCH', '/{record}', fn () => null);
        $route->bind($request);
        $route->setParameter('record', (string) $trashed->id);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
