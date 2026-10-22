<?php

namespace Basics13\Tests\Unit;

use Basics13\Tests\PackageIsolationTestCase;

/**
 * basics13 is the shared infrastructure every package builds on, so it names none of them.
 */
class PackageIsolationTest extends PackageIsolationTestCase
{
    protected function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function forbiddenInProductionCode(): array
    {
        return [
            'App\\',
            'Entities13',
            'Projects13',
            'EntityProjects13',
            'entities13',
            'projects13',
            'ENT_entities',
            'ENT_entity_types',
            'PRO_projects',
            'PRO_epics',
        ];
    }

    protected function forbiddenInTests(): array
    {
        return [
            'Entities13\\',
            'Projects13\\',
            'EntityProjects13\\',
        ];
    }

    protected function isolationAdvice(): string
    {
        return 'basics13 is domain-neutral, but its production code names a domain package. Keep the '
            .'shared piece generic and let the domain package configure it.';
    }
}
