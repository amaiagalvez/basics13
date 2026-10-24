<?php

namespace Basics13\Http\Requests;

use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RestoreRequest<EmailConfiguration>
 */
final class EmailConfigurationRestoreRequest extends RestoreRequest
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
