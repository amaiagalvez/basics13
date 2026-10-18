<?php

namespace Basics13\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;
use Basics13\Concerns\ProfileValidationRules;
use Basics13\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;

class UserRequest extends FormRequest
{
    use PasswordValidationRules;
    use ProfileValidationRules;
    use ResolvesUserModel;

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function authorize(): bool
    {
        $record = $this->route('user');

        if ($record instanceof Model) {
            return $this->user()?->can('update', $record) ?? false;
        }

        return $this->user()?->can('create', $this->userModelClass()) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|Password|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->editingId()),
            'password' => $this->passwordRule(),
        ];
    }

    /**
     * The account being edited, so the email rule ignores its own row. Null while creating.
     */
    private function editingId(): ?int
    {
        $record = $this->route('user');

        return $record instanceof Model ? (int) $record->getKey() : null;
    }

    /**
     * The password is required when the account is created and optional when it is edited: a form
     * that leaves it out keeps the current one. The confirmation rule of the shared trait stays.
     *
     * @return array<int, ValidationRule|Password|array<mixed>|string>
     */
    private function passwordRule(): array
    {
        $rules = $this->passwordRules();

        if ($this->route('user') instanceof Model) {
            $rules[0] = 'nullable';
        }

        return $rules;
    }
}
