@props([
    'request',
    'field',
    'id',
    'labels' => [],
])

{{--
    The rules of a field written out under its control, for the rules a user has to keep in mind
    while choosing a value (e.g. an end date after the start date), not only behind the info icon.
    Same source as the label's tooltip: the FormRequest, so the text cannot drift from the server.

    :id  id of the hint, for the control's aria-describedby. When the value breaks the rule, the
         hint itself turns red (see showClientError) instead of being repeated as an error.
--}}

@php
    $hint = implode(' ', (new \Basics13\Support\Validation\FieldHints($request, $labels))->for($field));
@endphp

@if ($hint !== '')
    <flux:text :id="$id" data-field-hint class="text-sm text-zinc-600 dark:text-zinc-400">{{ $hint }}</flux:text>
@endif
