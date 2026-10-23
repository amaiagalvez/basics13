<?php

namespace Basics13\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;

/**
 * Puts a trashed record back among the active ones.
 *
 * @template TRestoreable of Model
 *
 * @extends TrashRecordRequest<TRestoreable>
 */
abstract class RestoreRequest extends TrashRecordRequest
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

        $response = Gate::forUser($user)->inspect('restore', $this->record());

        return $response->allowed() ?: $response;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolve_name_conflict' => ['sometimes', 'boolean'],
        ];
    }
}
