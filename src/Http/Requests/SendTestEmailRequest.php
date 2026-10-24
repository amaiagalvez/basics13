<?php

namespace Basics13\Http\Requests;

use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A test email sent by hand from the configuration or template list to a single recipient.
 */
class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('email_configuration') ?? $this->route('email_template');

        return $record !== null && ($this->user()?->can('sendTest', $record) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'email', 'max:'.MaxLength::string()],
        ];
    }

    public function recipient(): string
    {
        return (string) $this->validated('recipient');
    }
}
