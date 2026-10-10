<?php

namespace Basics13\Tests\Unit\Http\Controllers;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Basics13\Tests\Fixtures\AuditActor;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Http\Controllers\ArchivedController;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArchivedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('test', fn () => null)->name('test.active');
        Route::get('test/archived', fn () => null)->name('test.archived');
        Gate::define('archive', fn (AuditActor $user, AuditedRecord $record): bool => true);
        Gate::define('activate', fn (AuditActor $user, AuditedRecord $record): bool => true);
    }

    public function test_archive_record_sets_active_to_false_and_redirects(): void
    {
        $controller = $this->controller();
        $record = AuditedRecord::factory()->create();
        $this->actingAs(AuditActor::factory()->create());

        $response = $controller->archive($record);

        $this->assertSame(route('test.active'), $response->getTargetUrl());
        $this->assertFalse((bool) $record->refresh()->active);
        $this->assertSame(__('basics13::messages.archived'), session('status'));
    }

    public function test_activate_record_sets_active_to_true_and_redirects(): void
    {
        $controller = $this->controller();
        $record = AuditedRecord::factory()->archived()->create();
        $this->actingAs(AuditActor::factory()->create());

        $response = $controller->activate($record);

        $this->assertSame(route('test.archived'), $response->getTargetUrl());
        $this->assertTrue((bool) $record->refresh()->active);
        $this->assertSame(__('basics13::messages.activated'), session('status'));
    }

    private function controller(): TestArchivedController
    {
        return new TestArchivedController;
    }
}

class TestArchivedController extends ArchivedController
{
    public function archive(AuditedRecord $record): RedirectResponse
    {
        return $this->archiveRecord($record);
    }

    public function activate(AuditedRecord $record): RedirectResponse
    {
        return $this->activateRecord($record);
    }

    protected function activeRoute(): string
    {
        return 'test.active';
    }

    protected function archivedRoute(): string
    {
        return 'test.archived';
    }
}
