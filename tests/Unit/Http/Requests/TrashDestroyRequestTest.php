<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Basics13\Tests\Fixtures\AuditActor;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Requests\TrashDestroyRequest;

class TrashDestroyRequestTest extends TestCase
{
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
     * @return TrashDestroyRequest<AuditedRecord>
     */
    private function request(): TrashDestroyRequest
    {
        return new /** @extends TrashDestroyRequest<AuditedRecord> */ class extends TrashDestroyRequest
        {
            protected function findTrashed(): AuditedRecord
            {
                return new AuditedRecord;
            }
        };
    }
}
