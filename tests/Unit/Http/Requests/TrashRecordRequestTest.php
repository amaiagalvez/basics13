<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Illuminate\Routing\Route;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Requests\TrashRecordRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrashRecordRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_returns_trashed_record(): void
    {
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = new class extends TrashRecordRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->whereKey($this->route('record'))->firstOrFail();
            }
        };

        $route = new Route('GET', '/{record}', fn () => null);
        $route->bind($request);
        $route->setParameter('record', (string) $trashed->id);
        $request->setRouteResolver(fn () => $route);

        $record = $request->record();

        $this->assertInstanceOf(AuditedRecord::class, $record);
        $this->assertSame($trashed->id, $record->id);
    }

    public function test_record_caches_the_result(): void
    {
        $trashed = AuditedRecord::factory()->trashed()->create();

        $request = new class extends TrashRecordRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            protected function findTrashed(): AuditedRecord
            {
                return AuditedRecord::onlyTrashed()->whereKey($this->route('record'))->firstOrFail();
            }
        };

        $route = new Route('GET', '/{record}', fn () => null);
        $route->bind($request);
        $route->setParameter('record', (string) $trashed->id);
        $request->setRouteResolver(fn () => $route);

        $record1 = $request->record();
        $record2 = $request->record();

        $this->assertSame($record1, $record2);
    }
}
