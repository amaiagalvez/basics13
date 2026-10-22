<?php

namespace Basics13\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every package's tests/bootstrap.php keeps temporary files inside the package storage. Each
 * package extends this and points it at its own root.
 */
abstract class BootstrapTestCase extends TestCase
{
    abstract protected function packageRoot(): string;

    public function test_temporary_directory_is_created_inside_package_storage(): void
    {
        $tempDir = $this->packageRoot().'/storage/.tmp';

        foreach (['TMPDIR', 'TEMP', 'TMP'] as $key) {
            $this->assertSame($tempDir, getenv($key));
        }

        $this->assertDirectoryExists($tempDir);
        $this->assertSame($tempDir, sys_get_temp_dir());
    }
}
