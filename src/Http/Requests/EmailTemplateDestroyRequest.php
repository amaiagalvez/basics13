<?php

namespace Basics13\Http\Requests;

use Basics13\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends TrashDestroyRequest<EmailTemplate>
 */
final class EmailTemplateDestroyRequest extends TrashDestroyRequest
{
    /**
     * @return EmailTemplate
     */
    protected function findTrashed(): Model
    {
        return EmailTemplate::onlyTrashed()
            ->findOrFail((int) $this->route('email_template'));
    }
}
