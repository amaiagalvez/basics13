<?php

namespace Basics13\Support\Testing;

use Basics13\Models\Role;
use Illuminate\Support\Env;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Dusk\TestCase as BaseTestCase;
use Facebook\WebDriver\Chrome\ChromeOptions;
use PHPUnit\Framework\Attributes\BeforeClass;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Basics13\Database\Seeders\BasicsAccessSeeder;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Illuminate\Database\Eloquent\Factories\Factory;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * `migrate:fresh` of DatabaseMigrations seeds the roles, permissions and menus, as `db:seed`
     * does in the application.
     */
    protected bool $seed = true;

    /** @var class-string */
    protected string $seeder = BasicsAccessSeeder::class;

    /** @param array<string, mixed> $attributes */
    protected function user(array $attributes = []): Model&Authenticatable
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! method_exists($model, 'factory')) {
            static::fail('Browser tests require an Eloquent authentication model with a factory.');
        }

        $factory = $model::factory();
        static::assertInstanceOf(Factory::class, $factory);

        $user = $factory->createOne($attributes);
        static::assertInstanceOf(Model::class, $user);
        static::assertInstanceOf(Authenticatable::class, $user);

        return $user;
    }

    /**
     * A user holding the `admin` system role, which manages accounts and roles.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function admin(array $attributes = []): Model&Authenticatable
    {
        $user = $this->user($attributes);

        if (! method_exists($user, 'assignRole')) {
            static::fail('The authentication model must use the Basics13\Concerns\HasRoles concern.');
        }

        $user->assignRole(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }

    protected function baseUrl(): string
    {
        $baseUrl = Env::get('DUSK_BASE_URL', config('app.url'));

        if (! is_string($baseUrl)) {
            static::fail('DUSK_BASE_URL must resolve to a string.');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            $chromeDriverPath = getenv('DUSK_CHROMEDRIVER_PATH');

            if (is_string($chromeDriverPath) && $chromeDriverPath !== '') {
                static::useChromedriver($chromeDriverPath);
            }

            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $chromeBinary = $_ENV['DUSK_CHROME_BINARY'] ?? Env::get('DUSK_CHROME_BINARY', '/usr/bin/chromium');
        $driverUrl = $_ENV['DUSK_DRIVER_URL'] ?? Env::get('DUSK_DRIVER_URL') ?? 'http://localhost:9515';

        if (! is_string($chromeBinary)) {
            static::fail('DUSK_CHROME_BINARY must resolve to a string.');
        }

        if (! is_string($driverUrl)) {
            static::fail('DUSK_DRIVER_URL must resolve to a string.');
        }

        $options = (new ChromeOptions)
            ->setBinary($chromeBinary)
            ->addArguments(collect([
                $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
                '--disable-search-engine-choice-screen',
                '--disable-smooth-scrolling',
                '--disable-dev-shm-usage',
                '--no-sandbox',
            ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
                return $items->merge([
                    '--disable-gpu',
                    '--headless=new',
                ]);
            })->all());

        return RemoteWebDriver::create(
            $driverUrl,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }
}
