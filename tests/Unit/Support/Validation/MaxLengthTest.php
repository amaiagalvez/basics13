<?php

namespace Basics13\Tests\Unit\Support\Validation;

use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Basics13\Support\Validation\MaxLength;

class MaxLengthTest extends TestCase
{
    public function test_string_returns_configured_value(): void
    {
        Config::set('basics13.validation.max_length.string', 191);

        $this->assertSame(191, MaxLength::string());
    }

    public function test_long_text_returns_configured_value(): void
    {
        Config::set('basics13.validation.max_length.longtext', 10000);

        $this->assertSame(10000, MaxLength::longText());
    }

    public function test_string_throws_when_config_missing(): void
    {
        Config::set('basics13.validation.max_length.string', null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('config/basics13.php must declare an integer validation.max_length.string.');

        MaxLength::string();
    }

    public function test_long_text_throws_when_config_missing(): void
    {
        Config::set('basics13.validation.max_length.longtext', null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('config/basics13.php must declare an integer validation.max_length.longtext.');

        MaxLength::longText();
    }

    public function test_string_throws_when_config_not_integer(): void
    {
        Config::set('basics13.validation.max_length.string', 'not-an-integer');

        $this->expectException(\LogicException::class);

        MaxLength::string();
    }
}
