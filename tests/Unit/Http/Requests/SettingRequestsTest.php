<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Basics13\Http\Requests\SettingIndexRequest;
use Basics13\Http\Requests\UpdateSettingsRequest;

class SettingRequestsTest extends TestCase
{
    public function test_a_guest_is_never_authorized(): void
    {
        $this->assertFalse((new SettingIndexRequest)->authorize());
        $this->assertFalse((new UpdateSettingsRequest)->authorize());
    }

    public function test_the_index_only_takes_a_bounded_package_name(): void
    {
        $this->assertSame(
            ['package' => ['nullable', 'string', 'max:'.config('basics13.validation.max_length.string')]],
            (new SettingIndexRequest)->rules(),
        );
    }
}
