<?php

namespace Basics13\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The docker-compose file that every application gets runs the helper services
 * against the scripts that live in this package (nothing is copied into the
 * application), so every path it references must exist here.
 */
final class ToolingComposeTest extends TestCase
{
    private const COMPOSE_FILE = __DIR__.'/../../tooling/docker-compose.yml';

    public function test_it_references_only_scripts_that_exist_in_the_package(): void
    {
        $compose = (string) file_get_contents(self::COMPOSE_FILE);

        // Match volume paths that reference files under tooling/:
        // - Legacy absolute paths: /packages/basics13/tooling/...
        // - Correct relative paths: ./tooling/... or ../tooling/...
        // (The test ignores other volume paths like ./:/app-docker or ../../packages:/packages)
        preg_match_all('#(?:/packages/basics13/tooling/|[./]+tooling/)([^\s:]+)#', $compose, $matches);

        $this->assertNotEmpty($matches[1], 'El compose canónico no referencia ningún script del paquete bajo tooling/.');

        foreach ($matches[1] as $relativePath) {
            $toolingDir = realpath(dirname(__DIR__, 2).'/tooling');
            if ($toolingDir === false) {
                $this->fail('Directorio tooling no encontrado.');
            }
            $resolvedPath = realpath($toolingDir.'/'.$relativePath);
            $this->assertTrue(
                $resolvedPath !== false && str_starts_with($resolvedPath, $toolingDir),
                sprintf('tooling/%s no existe en el paquete o escapa del directorio.', $relativePath)
            );
        }
    }
}
