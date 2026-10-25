@php
    $request = \Basics13\Http\Requests\EmailTemplateRequest::class;
    // English is the language every other one falls back to, so it is the only one asked for.
    $languages = [
        'en' => __('English'),
        'eu' => __('Basque'),
        'es' => __('Spanish'),
        'fr' => __('French'),
    ];
@endphp

<x-basics13::forms.tracked-resource prefix="email-template" context-field="_email_template_form"
    id-field="_email_template_id">
    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="name" test="email-template-name-info"
            for="email-template-name">
            {{ __('Name') }}
        </x-basics13::forms.field-label>
        <flux:input name="name" x-model="form.name" required minlength="2" autofocus
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
            id="email-template-name" data-test="email-template-name" />
        <flux:error name="name" />
    </flux:field>

    <flux:text>{{ __('Write parameters as :name:. :subject: and :content: stand for the subject and the body of each email; :banner: and :legal_text: place the banner and the legal text inside the layout.') }}</flux:text>

    @foreach ($languages as $locale => $language)
        <flux:fieldset>
            <flux:legend>{{ $language }}</flux:legend>

            <div class="flex flex-col gap-4">
                <flux:field>
                    <x-basics13::forms.field-label :request="$request" :field="'subject_' . $locale"
                        :test="'email-template-subject_' . $locale . '-info'" :for="'email-template-subject_' . $locale">
                        {{ __('Subject') }}
                    </x-basics13::forms.field-label>
                    <flux:input name="subject_{{ $locale }}" x-model="form.subject_{{ $locale }}"
                        :required="$locale === 'en'"
                        maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
                        id="email-template-subject_{{ $locale }}" data-test="email-template-subject_{{ $locale }}" />
                    <flux:error name="subject_{{ $locale }}" />
                </flux:field>

                <flux:field>
                    <x-basics13::forms.field-label :request="$request" :field="'body_' . $locale"
                        :test="'email-template-body_' . $locale . '-info'" :for="'email-template-body_' . $locale">
                        {{ __('Body (HTML)') }}
                    </x-basics13::forms.field-label>
                    <flux:textarea name="body_{{ $locale }}" x-model="form.body_{{ $locale }}" rows="6"
                        :required="$locale === 'en'"
                        maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
                        id="email-template-body_{{ $locale }}" data-test="email-template-body_{{ $locale }}" />
                    <flux:error name="body_{{ $locale }}" />
                </flux:field>
            </div>
        </flux:fieldset>
    @endforeach

    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="banner" test="email-template-banner-info"
            for="email-template-banner">
            {{ __('Banner image URL') }}
        </x-basics13::forms.field-label>
        <flux:input name="banner" type="url" x-model="form.banner"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
            id="email-template-banner" data-test="email-template-banner" />
        <flux:error name="banner" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="legal_text" test="email-template-legal_text-info"
            for="email-template-legal_text">
            {{ __('Legal text') }}
        </x-basics13::forms.field-label>
        <flux:textarea name="legal_text" x-model="form.legal_text" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="email-template-legal_text" data-test="email-template-legal_text" />
        <flux:error name="legal_text" />
    </flux:field>

    <flux:field variant="inline">
        <input type="hidden" name="default" value="0">
        <flux:checkbox name="default" value="1" x-model="form.default" id="email-template-default"
            data-test="email-template-default" />
        <flux:label for="email-template-default">{{ __('Use as the default template') }}</flux:label>
        <flux:error name="default" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="notes" test="email-template-notes-info"
            for="email-template-notes">
            {{ __('Notes') }}
        </x-basics13::forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="email-template-notes" data-test="email-template-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>
