@props(['prefix'])

{{--
    Success shows as a Flux toast. The toast has no live region of its own, so the message also sits
    in a visually hidden status element, which is what screen readers and the browser tests read.
    The toast waits a tick so the persisted toast group is listening before it is fired.
--}}
@if (session('status'))
    <div class="sr-only" role="status" aria-live="polite" data-test="{{ $prefix }}-status"
        x-data x-init="$nextTick(() => $flux.toast({ text: @js(session('status')), variant: 'success' }))">
        {{ session('status') }}</div>
@endif

{{-- A blocking error stays on the page until the user acts on it. --}}
@if (session('error'))
    <flux:callout class="rounded-xl" icon="exclamation-triangle" variant="danger" role="alert"
        :data-test="$prefix.'-error'">
        {{ session('error') }}</flux:callout>
@endif
