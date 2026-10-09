<?php

namespace Basics13\Tests\Unit\Support\Validation;

use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\BoundedLengthRequest;
use Basics13\Support\Validation\FieldHints;
use Basics13\Support\Validation\MaxLength;

final class FieldHintsTest extends TestCase
{
    public function test_a_field_bounded_at_one_end_only_announces_that_end(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $this->assertSame([
            __('Use at least :min characters.', ['min' => 2]),
        ], $hints->for('code'));
        $this->assertSame([
            __('Use at most :max characters.', ['max' => 8]),
        ], $hints->for('nickname'));
    }

    public function test_a_field_that_is_not_text_announces_no_length(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $this->assertSame([], $hints->for('quantity'));
    }
}