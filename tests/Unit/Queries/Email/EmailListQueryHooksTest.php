<?php

namespace Basics13\Tests\Unit\Queries\Email;

use ReflectionMethod;
use Basics13\Tests\TestCase;
use Basics13\Models\EmailTemplate;
use Basics13\Queries\ListQueryBase;
use Basics13\Models\EmailConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use Basics13\Queries\EmailTemplates\EmailTemplateListQuery;
use Basics13\Queries\EmailConfigurations\EmailConfigurationListQuery;

/**
 * The email lists search by their own columns, so the hooks the base declares never come up on
 * their path; they still have to answer with their model and a no-op integration.
 */
final class EmailListQueryHooksTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string}>
     */
    public static function queries(): array
    {
        return [
            'templates' => [EmailTemplateListQuery::class, EmailTemplate::class],
            'configurations' => [EmailConfigurationListQuery::class, EmailConfiguration::class],
        ];
    }

    /**
     * @param  class-string  $queryClass
     * @param  class-string  $modelClass
     */
    #[DataProvider('queries')]
    public function test_the_query_names_its_model_and_adds_no_search_columns(string $queryClass, string $modelClass): void
    {
        $query = new $queryClass;
        $this->assertInstanceOf(ListQueryBase::class, $query);

        $this->assertSame($modelClass, (new ReflectionMethod($query, 'getModelClass'))->invoke($query));
        $this->assertSame(['name'], (new ReflectionMethod($query, 'searchColumns'))->invoke($query, 'active', ['name']));
        $this->assertSame([], $query->searchableFields('active'));
    }
}
