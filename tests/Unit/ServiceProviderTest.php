<?php

namespace Basics13\Tests\Unit;

use Basics13\Tests\TestCase;
use Basics13\ServiceProvider;

/**
 * The tooling files live in the package and are published into every application
 * that uses it, so no application keeps a hand-written copy of them.
 */
final class ServiceProviderTest extends TestCase
{
    /**
     * Files of the basics13-tooling publish group, in publish order.
     *
     * @var array<int, string>
     */
    private const TOOLING_FILES = [
        'pint.json',
        'phpstan.neon',
        'phpunit.xml',
        'phpunit.dusk.xml',
        'boost.json',
        'docker-compose.yml',
        'Dockerfile.dusk',
    ];

    public function test_it_publishes_every_tooling_file(): void
    {
        $paths = ServiceProvider::pathsToPublish(null, 'basics13-tooling');

        $this->assertSame(self::TOOLING_FILES, array_map('basename', array_keys($paths)));
        $this->assertSame(self::TOOLING_FILES, array_map('basename', array_values($paths)));
    }

    public function test_published_tooling_files_exist_in_the_package(): void
    {
        $sources = array_keys(ServiceProvider::pathsToPublish(null, 'basics13-tooling'));

        foreach ($sources as $source) {
            $this->assertFileExists($source);
        }
    }
}
