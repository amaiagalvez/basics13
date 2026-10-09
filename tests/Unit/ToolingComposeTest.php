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
        $compose = file_get_contents(self::COMPOSE_FILE);

        preg_match_all('#/packages/basics13/tooling/([^\s:]+)#', $compose, $matches);

        $this->assertNotEmpty($matches[1], 'El compose canónico no referencia ningún script del paquete.');

        foreach ($matches[1] as $relativePath) {
            $this->assertTrue(
                file_exists(dirname(__DIR__, 2).'/tooling/'.$relativePath),
                sprintf('tooling/%s no existe en el paquete.', $relativePath)
            );
        }
    }
}
