<?php

namespace Basics13\Http\Requests;

use Basics13\Concerns\ResolvesUserModel;

class UserSelectOptionsRequest extends SearchableSelectOptionsRequest
{
    use ResolvesUserModel;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', $this->userModelClass()) ?? false;
    }
}
