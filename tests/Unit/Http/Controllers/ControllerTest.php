<?php

namespace Basics13\Tests\Unit\Http\Controllers;

use Illuminate\View\View;
use Basics13\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\Controller;
use Basics13\Tests\Fixtures\AuditedRecord;
use Illuminate\Support\Facades\View as ViewFacade;

class ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ViewFacade::addNamespace('basics13', __DIR__.'/../../../Fixtures/views');
    }

    public function test_list_view_returns_full_view_when_no_fragment_header(): void
    {
        $controller = new TestController;
        $request = Request::create('/', 'GET');

        $view = $controller->listing($request);

        $this->assertInstanceOf(View::class, $view);
    }

    public function test_list_view_returns_fragment_when_fragment_header_present(): void
    {
        $controller = new TestController;
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_LIST_FRAGMENT' => 'true']);

        $result = $controller->listing($request);

        $this->assertSame("Test results\n", $result);
    }

    public function test_deleted_name_conflict_returns_json_when_expects_json(): void
    {
        $controller = new TestController;
        $trashed = AuditedRecord::factory()->make(['id' => 1, 'name' => 'Conflict Name']);
        $request = Request::create('/', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response = $controller->conflict($request, $trashed);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame([
            'message' => __('basics13::messages.deleted_name_conflict', ['name' => 'Conflict Name']),
            'errors' => [
                'name' => [__('basics13::messages.deleted_name_conflict', ['name' => 'Conflict Name'])],
            ],
        ], $response->getData(true));
    }

    public function test_deleted_name_conflict_returns_redirect_with_conflict_when_not_json(): void
    {
        Route::get('records', fn () => null)->name('auditedrecords.index');
        $controller = new TestController;
        $trashed = AuditedRecord::factory()->make(['id' => 1, 'name' => 'Conflict Name']);
        $request = Request::create('/', 'POST');

        $response = $controller->conflict($request, $trashed);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('auditedrecords.index'), $response->getTargetUrl());
        $this->assertSame(['id' => 1, 'name' => 'Conflict Name'], session('deleted_auditedrecord_conflict'));
    }
}

class TestController extends Controller
{
    public function listing(Request $request): View|string
    {
        $view = 'basics13::test-list';

        if (! ViewFacade::exists($view)) {
            throw new \LogicException('The test list view must be registered.');
        }

        /** @var view-string $view */
        return $this->listView($request, $view, []);
    }

    public function conflict(Request $request, AuditedRecord $record): RedirectResponse|JsonResponse
    {
        return $this->deletedNameConflict($request, $record);
    }
}
