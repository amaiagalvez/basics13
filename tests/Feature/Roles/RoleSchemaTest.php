<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleSchemaTest extends FeatureTestCase
{
    use AssertsRoleSchema;
    use RefreshDatabase;
}
