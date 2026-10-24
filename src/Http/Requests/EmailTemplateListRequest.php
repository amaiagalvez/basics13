<?php

namespace Basics13\Http\Requests;

use Basics13\Models\EmailTemplate;

class EmailTemplateListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmailTemplate::class) ?? false;
    }
}
