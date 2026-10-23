<?php

namespace Basics13\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;

/**
 * Deletes a trashed record for good. The route carries no body, so only the ability is checked.
 *
 * @template TDeletable of Model
 *
 * @extends TrashRecordRequest<TDeletable>
 */
abstract class TrashDestroyRequest extends TrashRecordRequest
{
    /**
     * The policy's response is handed back as it is, so a policy that answers "not found" for a
     * record the user may not even know about is not turned into a 403.
     */
    public function authorize(): bool|Response
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $response = Gate::forUser($user)->inspect('forceDelete', $this->record());

        return $response->allowed() ?: $response;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
