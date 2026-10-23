<?php

namespace Basics13\Http\Requests;

use Basics13\Models\SessionLog;

class SessionLogListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SessionLog::class) ?? false;
    }
}
