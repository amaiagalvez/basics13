<?php

namespace Basics13\Http\Requests;

use Basics13\Models\EmailConfiguration;

class EmailConfigurationListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmailConfiguration::class) ?? false;
    }
}
