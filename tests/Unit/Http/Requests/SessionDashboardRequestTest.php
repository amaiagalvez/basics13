<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Basics13\Http\Requests\SessionDashboardRequest;

class SessionDashboardRequestTest extends TestCase
{
    public function test_a_guest_is_never_authorized(): void
    {
        $this->assertFalse((new SessionDashboardRequest)->authorize());
    }

    public function test_the_period_is_one_of_the_offered_lengths(): void
    {
        $this->assertSame(30, SessionDashboardRequest::create('/', 'GET', ['period' => '30'])->period());
        $this->assertSame(90, SessionDashboardRequest::create('/', 'GET', ['period' => '90'])->period());
    }

    public function test_any_other_period_falls_back_to_seven_days(): void
    {
        foreach ([[], ['period' => '45'], ['period' => 'abc'], ['period' => ['7']], ['period' => '7.0'], ['period' => '']] as $query) {
            $this->assertSame(7, SessionDashboardRequest::create('/', 'GET', $query)->period(), json_encode($query) ?: '');
        }
    }
}
