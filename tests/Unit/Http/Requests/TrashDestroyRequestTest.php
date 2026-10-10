<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Basics13\Tests\Fixtures\AuditActor;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Requests\TrashDestroyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrashDestroyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_returns_empty_array(): void
    {
        $request = new class extends TrashDestroyRequest
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

        $this->assertSame([], $rules);
    }

    public function test_authorize_returns_true_when_user_can_force_delete(): void
    {
        Gate::define('forceDelete', fn (AuditActor $user, AuditedRecord $record): bool => true);
        $user = AuditActor::factory()->create();
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = new class extends TrashDestroyRequest
        {
            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->whereKey($this->route('record'))->firstOrFail();
            }
        };

        $route = new Route('DELETE', '/{record}', fn () => null);
        $route->bind($request);
        $route->setParameter('record', (string) $trashed->id);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_when_no_user(): void
    {
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = new class extends TrashDestroyRequest
        {
            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->whereKey($this->route('record'))->firstOrFail();
            }
        };

        $route = new Route('DELETE', '/{record}', fn () => null);
        $route->bind($request);
        $route->setParameter('record', (string) $trashed->id);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
