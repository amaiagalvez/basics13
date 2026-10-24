{{--
    Asks for the recipient of a test email. Driven by the parent Alpine "sendTest" state, which the
    "modal" row action fills through openSendTest() with the URL and the name of the row.
--}}
@props(['prefix'])

<flux:modal :name="$prefix.'-send-test'" :aria-labelledby="$prefix.'-send-test-heading'" class="max-w-md">
    <form method="POST" x-bind:action="sendTest.action" class="flex flex-col gap-6"
        x-data="{ isSubmitting: false }"
        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true">
        @csrf
        {{-- Sent back with old() so the modal reopens on the same row after a validation error. --}}
        <input type="hidden" name="_send_test_action" x-bind:value="sendTest.action">
        <input type="hidden" name="_send_test_name" x-bind:value="sendTest.name">
        <div>
            <flux:heading :id="$prefix.'-send-test-heading'" level="2" size="lg" class="pe-8">
                {{ __('Send test email') }}
            </flux:heading>
            <flux:text class="mt-2" x-text="sendTest.name"></flux:text>
        </div>

        <flux:field>
            <flux:label :for="$prefix.'-send-test-recipient'">
                {{ __('Recipient') }} <span class="text-red-600" aria-hidden="true">*</span>
            </flux:label>
            <flux:input type="email" name="recipient" required autofocus
                maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
                :id="$prefix.'-send-test-recipient'" :data-test="$prefix.'-send-test-recipient'" />
            <flux:error name="recipient" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" icon="paper-airplane" x-bind:disabled="isSubmitting"
                :data-test="$prefix.'-send-test-submit'">
                {{ __('Send test email') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
