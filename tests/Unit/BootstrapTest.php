<?php

namespace Basics13\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function test_temporary_directory_is_created_inside_package_storage(): void
    {
        $tempDir = dirname(__DIR__, 2).'/storage/.tmp';

        foreach (['TMPDIR', 'TEMP', 'TMP'] as $key) {
            $this->assertSame($tempDir, getenv($key));
        }

        $this->assertDirectoryExists($tempDir);
        $this->assertSame($tempDir, sys_get_temp_dir());
    }
}
