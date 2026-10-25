<?php

namespace Basics13\Tests\Feature\Forms;

use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Blade;

class FieldLabelInfoTest extends FeatureTestCase
{
    public function test_the_info_button_is_named_by_a_short_label_not_by_the_whole_tooltip(): void
    {
        $html = Blade::render(
            '<x-basics13::forms.field-label :request="$request" field="title" test="event-title-info">Title</x-basics13::forms.field-label>',
            ['request' => \Basics13\Http\Requests\PersonalEventRequest::class],
        );

        $this->assertStringContainsString('data-test="event-title-info"', $html);
        $this->assertStringContainsString('aria-label="'.__('Information about this field').'"', $html);
    }
}
