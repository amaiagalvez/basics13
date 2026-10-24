<?php

namespace Basics13\Http\Requests;

use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends TrashDestroyRequest<EmailConfiguration>
 */
final class EmailConfigurationDestroyRequest extends TrashDestroyRequest
{
    /**
     * @return EmailConfiguration
     */
    protected function findTrashed(): Model
    {
        return EmailConfiguration::onlyTrashed()
            ->findOrFail((int) $this->route('email_configuration'));
    }
}
