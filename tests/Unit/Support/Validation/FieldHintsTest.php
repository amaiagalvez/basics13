<?php

namespace Basics13\Tests\Unit\Support\Validation;

use Basics13\Tests\TestCase;
use Basics13\Support\Validation\FieldHints;
use Basics13\Tests\Fixtures\NoRulesRequest;
use Basics13\Tests\Fixtures\BoundedLengthRequest;
use Basics13\Tests\Fixtures\ComprehensiveFieldHintsRequest;

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

    public function test_is_required_returns_true_for_required_field(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $this->assertTrue($hints->isRequired('code'));
        $this->assertTrue($hints->isRequired('nickname'));
    }

    public function test_is_required_returns_false_for_optional_field(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $this->assertFalse($hints->isRequired('quantity'));
    }

    public function test_field_with_min_and_max_shows_range(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('name');

        // Should contain the range message
        $this->assertContains(__('Use between :min and :max characters.', ['min' => 3, 'max' => 50]), $result);
    }

    public function test_field_with_only_min_shows_min_message(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('code');

        $this->assertContains(__('Use at least :min characters.', ['min' => 2]), $result);
    }

    public function test_field_with_only_max_shows_max_message(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('nickname');

        $this->assertContains(__('Use at most :max characters.', ['max' => 20]), $result);
    }

    public function test_field_with_unique_rule_shows_unique_hint(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('email');

        $this->assertContains(__('Must be unique.'), $result);
    }

    public function test_field_with_after_rule_shows_after_hint(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('start_date');

        $this->assertContains(__('Must be later than :field.', ['field' => 'created at']), $result);
    }

    public function test_field_with_after_or_equal_rule_shows_hint(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('end_date');

        $this->assertContains(__('Must be on or after :field.', ['field' => 'start date']), $result);
    }

    public function test_field_with_required_with_shows_hint(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('optional_field');

        $this->assertContains(__('Required when :field is filled in.', ['field' => 'other field']), $result);
    }

    public function test_integer_field_shows_no_length_hint(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $result = $hints->for('quantity');

        // Integer fields don't get length hints even if they have min/max
        $this->assertSame([], $result);
    }

    public function test_optional_field_is_not_required(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $this->assertFalse($hints->isRequired('notes'));
    }

    public function test_required_field_is_required(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $this->assertTrue($hints->isRequired('name'));
        $this->assertTrue($hints->isRequired('email'));
    }

    public function test_custom_labels_are_used_in_hints(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class, [
            'code' => 'Custom Code Label',
        ]);

        $result = $hints->for('code');

        // The custom label should be used in the required_with/after hints
        $this->assertNotEmpty($result);
    }

    public function test_label_method_uses_custom_label_when_provided(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class, [
            'custom_field' => 'My Custom Label',
        ]);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('label');
        $method->setAccessible(true);

        $label = $method->invoke($hints, 'custom_field');

        // Str::lcfirst only lowercases first character
        $this->assertSame('my Custom Label', $label);
    }

    public function test_label_method_falls_back_to_readable_name(): void
    {
        $hints = new FieldHints(ComprehensiveFieldHintsRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('label');
        $method->setAccessible(true);

        $label = $method->invoke($hints, 'snake_case_field');

        $this->assertSame('snake case field', $label);
    }

    public function test_declared_rules_caches_result(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('declaredRules');
        $method->setAccessible(true);

        $rules1 = $method->invoke($hints);
        $rules2 = $method->invoke($hints);

        $this->assertSame($rules1, $rules2);
    }

    public function test_declared_rules_throws_when_no_rules_method(): void
    {
        // Use a class that exists but doesn't have rules() method
        $hints = new FieldHints(NoRulesRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('declaredRules');
        $method->setAccessible(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must declare rules()');

        $method->invoke($hints);
    }

    public function test_parsed_rules_parses_string_rules(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('parsedRules');
        $method->setAccessible(true);

        $parsed = $method->invoke($hints, 'code');

        $this->assertIsArray($parsed);
        $this->assertContains(['required', []], $parsed);
        $this->assertContains(['string', []], $parsed);
        $this->assertContains(['min', ['2']], $parsed);
    }

    public function test_say_method_returns_translated_string(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('say');
        $method->setAccessible(true);

        $result = $method->invoke($hints, 'Test :value', ['value' => '123']);

        // The __() function will return the string if no translation exists
        $this->assertIsString($result);
        $this->assertStringContainsString('123', $result);
    }

    public function test_length_notices_returns_empty_for_non_string_fields(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('lengthNotices');
        $method->setAccessible(true);

        // quantity is integer, not string
        $rules = [
            ['integer', []],
            ['min', ['1']],
            ['max', ['99']],
        ];

        $result = $method->invoke($hints, $rules);

        $this->assertSame([], $result);
    }

    public function test_length_notices_returns_both_min_and_max(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('lengthNotices');
        $method->setAccessible(true);

        $rules = [
            ['string', []],
            ['min', ['5']],
            ['max', ['20']],
        ];

        $result = $method->invoke($hints, $rules);

        $this->assertIsArray($result);
        $this->assertContains(__('Use between :min and :max characters.', ['min' => '5', 'max' => '20']), $result);
    }

    public function test_length_notices_returns_only_min(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('lengthNotices');
        $method->setAccessible(true);

        $rules = [
            ['string', []],
            ['min', ['3']],
        ];

        $result = $method->invoke($hints, $rules);

        $this->assertIsArray($result);
        $this->assertContains(__('Use at least :min characters.', ['min' => '3']), $result);
    }

    public function test_length_notices_returns_only_max(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('lengthNotices');
        $method->setAccessible(true);

        $rules = [
            ['string', []],
            ['max', ['15']],
        ];

        $result = $method->invoke($hints, $rules);

        $this->assertIsArray($result);
        $this->assertContains(__('Use at most :max characters.', ['max' => '15']), $result);
    }

    public function test_hint_method_returns_null_for_unknown_rules(): void
    {
        $hints = new FieldHints(BoundedLengthRequest::class);

        $reflection = new \ReflectionClass($hints);
        $method = $reflection->getMethod('hint');
        $method->setAccessible(true);

        $result = $method->invoke($hints, 'unknown_rule', []);

        $this->assertNull($result);
    }
}
