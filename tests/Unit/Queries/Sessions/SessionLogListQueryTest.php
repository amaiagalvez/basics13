<?php

namespace Basics13\Tests\Unit\Queries\Sessions;

use ReflectionMethod;
use Basics13\Tests\TestCase;
use Basics13\Models\SessionLog;
use Basics13\Queries\Sessions\SessionLogListQuery;

/**
 * The hooks the base declares and the date operators the advanced search never sends.
 */
class SessionLogListQueryTest extends TestCase
{
    public function test_the_query_answers_the_base_with_the_session_log_model(): void
    {
        $method = new ReflectionMethod(SessionLogListQuery::class, 'getModelClass');

        $this->assertSame(SessionLog::class, $method->invoke(new SessionLogListQuery));
    }

    public function test_the_integration_appends_no_search_column(): void
    {
        $method = new ReflectionMethod(SessionLogListQuery::class, 'integration');

        $this->assertSame(['BAS_sessions.login'], $method->invoke(new SessionLogListQuery)->searchColumns('active', ['BAS_sessions.login']));
    }

    public function test_an_unknown_date_operator_filters_nothing(): void
    {
        $query = SessionLog::query();
        $method = new ReflectionMethod(SessionLogListQuery::class, 'applyDateFilter');

        $method->invoke(new SessionLogListQuery, $query, 'BAS_sessions.created_at', 'is_empty', '2026-10-05');

        $this->assertSame([], $query->getQuery()->wheres);
    }
}
