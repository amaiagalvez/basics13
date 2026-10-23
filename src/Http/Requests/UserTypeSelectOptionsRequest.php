<?php

namespace Basics13\Http\Requests;

use Basics13\Models\UserType;

class UserTypeSelectOptionsRequest extends SearchableSelectOptionsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', UserType::class) ?? false;
    }
}
