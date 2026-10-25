<?php

namespace Basics13\Tests\Unit\Transformers;

use ReflectionMethod;
use Basics13\Tests\TestCase;
use Basics13\Models\EmailConfiguration;
use Basics13\Transformers\EmailConfigurationListTransformer;

final class EmailConfigurationListTransformerTest extends TestCase
{
    public function test_the_default_configuration_explains_why_it_cannot_be_force_deleted(): void
    {
        $transformer = new EmailConfigurationListTransformer;

        $this->assertSame(
            __('The default one cannot be deleted permanently. Mark another one as the default first.'),
            (new ReflectionMethod($transformer, 'getForceDeleteBlockHint'))->invoke($transformer, new EmailConfiguration(['default' => true])),
        );
    }
}
