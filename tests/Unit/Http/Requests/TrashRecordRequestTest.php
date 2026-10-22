<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Closure;
use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Requests\TrashRecordRequest;

class TrashRecordRequestTest extends TestCase
{
    public function test_record_returns_trashed_record(): void
    {
        $trashed = new AuditedRecord;

        $this->assertSame($trashed, $this->request(fn (): AuditedRecord => $trashed)->record());
    }

    public function test_record_caches_the_result(): void
    {
        $lookups = 0;
        $request = $this->request(function () use (&$lookups): AuditedRecord {
            $lookups++;

            return new AuditedRecord;
        });

        $record1 = $request->record();
        $record2 = $request->record();

        $this->assertSame($record1, $record2);
        $this->assertSame(1, $lookups);
    }

    /**
     * @param  Closure(): AuditedRecord  $findTrashed
     * @return TrashRecordRequest<AuditedRecord>
     */
    private function request(Closure $findTrashed): TrashRecordRequest
    {
        return new /** @extends TrashRecordRequest<AuditedRecord> */ class($findTrashed) extends TrashRecordRequest
        {
            /**
             * @param  Closure(): AuditedRecord  $findTrashed
             */
            public function __construct(private readonly Closure $findTrashed)
            {
                parent::__construct();
            }

            public function authorize(): bool
            {
                return true;
            }

            protected function findTrashed(): AuditedRecord
            {
                return ($this->findTrashed)();
            }
        };
    }
}
