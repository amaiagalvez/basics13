<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Basics13\Tests\Fixtures\AuditActor;
use Basics13\Http\Requests\RestoreRequest;
use Basics13\Tests\Fixtures\AuditedRecord;

class RestoreRequestTest extends TestCase
{
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

        $request = $this->request();
        $request->setUserResolver(fn () => new AuditActor);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_when_no_user(): void
    {
        $request = $this->request();
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }

    /**
     * @return RestoreRequest<AuditedRecord>
     */
    private function request(): RestoreRequest
    {
        return new /** @extends RestoreRequest<AuditedRecord> */ class extends RestoreRequest
        {
            protected function findTrashed(): AuditedRecord
            {
                return new AuditedRecord;
            }
        };
    }
}
