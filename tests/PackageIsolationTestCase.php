<?php

namespace Basics13\Tests;

use Generator;
use SplFileInfo;
use ReflectionClass;
use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;

/**
 * A package has to be usable without the packages it must not know, and the only thing that proves
 * it is the absence of every reference to them. Composer's lockfile does not prove anything here:
 * the host resolves a single dependency graph for all of them, so a reference that only fails at
 * runtime still installs cleanly. Each package extends this and lists what it must not name.
 */
abstract class PackageIsolationTestCase extends TestCase
{
    /**
     * Directories whose contents are production code. Tests, fixtures and vendor are excluded on
     * purpose: a test is allowed to know what the integration adds.
     *
     * @var list<string>
     */
    private const PRODUCTION_DIRECTORIES = ['src', 'resources', 'routes', 'config'];

    abstract protected function packageRoot(): string;

    /**
     * Namespaces, table names, route names and payload keys production code must never contain.
     *
     * @return list<non-empty-string>
     */
    abstract protected function forbiddenInProductionCode(): array;

    /**
     * What the package's own tests must not contain either; empty when its tests may know everything.
     *
     * @return list<non-empty-string>
     */
    abstract protected function forbiddenInTests(): array;

    /**
     * Where to move whatever needs a forbidden package, shown when the production scan fails.
     */
    abstract protected function isolationAdvice(): string;

    public function test_production_code_never_references_a_forbidden_package(): void
    {
        $offences = [];

        foreach ($this->productionFiles() as $file) {
            foreach ($this->offencesIn($file) as $offence) {
                $offences[] = $offence;
            }
        }

        $this->assertSame(
            [],
            $offences,
            $this->isolationAdvice().PHP_EOL.PHP_EOL.implode(PHP_EOL, $offences),
        );
    }

    public function test_the_isolation_test_scans_something(): void
    {
        // A scan that silently stops finding files would make the assertion above pass forever.
        $this->assertGreaterThan(10, iterator_count($this->productionFiles()));
    }

    public function test_the_package_tests_do_not_require_a_forbidden_package(): void
    {
        if ($this->forbiddenInTests() === []) {
            $this->markTestSkipped('The package tests may reference every package.');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->packageRoot().'/tests/'),
        );
        // The subclass lists the forbidden strings, so it is the one file that has to name them.
        $ownFile = (new ReflectionClass(static::class))->getFileName();

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || $file->getPathname() === $ownFile) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $this->assertIsString($source);

            foreach ($this->forbiddenInTests() as $needle) {
                $this->assertStringNotContainsString($needle, $source, $file->getPathname());
            }
        }
    }

    /**
     * @return Generator<int, SplFileInfo>
     */
    private function productionFiles(): iterable
    {
        foreach (self::PRODUCTION_DIRECTORIES as $directory) {
            $path = $this->packageRoot().'/'.$directory;

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'json'], true)) {
                    yield $file;
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function offencesIn(SplFileInfo $file): array
    {
        $contents = (string) file_get_contents($file->getPathname());
        $relative = str_replace($this->packageRoot().'/', '', $file->getPathname());
        $offences = [];

        foreach ($this->forbiddenInProductionCode() as $needle) {
            $line = $this->lineWith($contents, $needle);

            if ($line !== null) {
                $offences[] = sprintf('%s:%d references "%s"', $relative, $line, $needle);
            }
        }

        return $offences;
    }

    private function lineWith(string $contents, string $needle): ?int
    {
        $position = strpos($contents, $needle);

        return $position === false
            ? null
            : substr_count($contents, PHP_EOL, 0, $position) + 1;
    }
}
