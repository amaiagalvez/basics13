<?php

namespace Basics13\Tests\Unit\Support\Testing;

use stdClass;
use Basics13\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Livewire\LivewireServiceProvider;
use Basics13\Tests\Fixtures\AuditActor;
use Illuminate\Database\Eloquent\Model;
use Basics13\Support\Testing\DuskTestCase;
use PHPUnit\Framework\AssertionFailedError;
use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Attributes\DataProvider;
use Orchestra\Testbench\Concerns\CreatesApplication;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

/**
 * The package Dusk base class reduced to what a test without a browser can drive.
 *
 * It has to extend the shipped class rather than stand in for it, because both methods under test
 * are protected: only a subclass reaches them. prepare() is overridden because the chromedriver
 * bootstrap it performs belongs to a browser suite.
 */
abstract class DuskTestCaseHarness extends DuskTestCase
{
    use CreatesApplication;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [
            LivewireServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    public static function prepare(): void
    {
        //
    }

    /** @param array<string, mixed> $attributes */
    public function browserUser(array $attributes = []): Model&Authenticatable
    {
        return $this->user($attributes);
    }

    public function browserBaseUrl(): string
    {
        return $this->baseUrl();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Every browser suite leans on these two methods before it opens a page, and unlike
        // prepare() and driver() they need no browser, so they are covered from here.
        $this->app->make(ConsoleKernel::class)->call('migrate', [
            '--path' => [
                dirname(__DIR__, 4).'/database/migrations',
                dirname(__DIR__, 3).'/Fixtures',
            ],
            '--realpath' => true,
            '--force' => true,
        ]);

        config(['auth.providers.users.model' => AuditActor::class]);
    }

    protected function tearDown(): void
    {
        unset($_ENV['DUSK_BASE_URL']);
        putenv('DUSK_BASE_URL');

        parent::tearDown();
    }
}

/**
 * Test the browser-independent half of the package Dusk base class.
 *
 * prepare() and driver() need a live chromedriver, so the browser suites of the packages that
 * extend this class cover them and this package excludes the file from its own coverage.
 */
final class DuskTestCaseTest extends DuskTestCaseHarness
{
    public function test_user_creates_the_authentication_model_configured_for_the_provider(): void
    {
        $user = $this->browserUser(['name' => 'Mikel']);

        $this->assertInstanceOf(AuditActor::class, $user);
        $this->assertInstanceOf(Authenticatable::class, $user);
        $this->assertSame('Mikel', DB::table('users')->where('id', $user->getKey())->value('name'));
    }

    public function test_user_creates_a_separate_record_on_every_call(): void
    {
        $this->assertNotSame($this->browserUser()->getKey(), $this->browserUser()->getKey());
    }

    /**
     * The provider model a browser suite cannot be driven with: both leave user() without an
     * Eloquent model that knows how to build itself.
     *
     * @return array<string, array{string}>
     */
    public static function unusableAuthModels(): array
    {
        return [
            'empty string' => [''],
            'not an Eloquent model' => [stdClass::class],
        ];
    }

    #[DataProvider('unusableAuthModels')]
    public function test_user_fails_when_the_provider_model_cannot_produce_an_authenticatable(string $model): void
    {
        config(['auth.providers.users.model' => $model]);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Browser tests require an Eloquent authentication model with a factory.');

        $this->browserUser();
    }

    public function test_base_url_comes_from_the_dusk_environment_and_drops_trailing_slashes(): void
    {
        $_ENV['DUSK_BASE_URL'] = 'http://dusk.test:9515///';

        $this->assertSame('http://dusk.test:9515', $this->browserBaseUrl());
    }

    public function test_base_url_falls_back_to_the_application_url(): void
    {
        config(['app.url' => 'http://fallback.test/']);

        $this->assertSame('http://fallback.test', $this->browserBaseUrl());
    }
}
