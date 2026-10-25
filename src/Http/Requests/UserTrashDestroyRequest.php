<?php

namespace Basics13\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;

/**
 * @extends TrashDestroyRequest<Model>
 */
class UserTrashDestroyRequest extends TrashDestroyRequest
{
    use ResolvesUserModel;

    /**
     * Users are never deleted permanently, whoever asks. The refusal comes before the policy, so the
     * super-admin bypass of the Gate does not reach it.
     */
    public function authorize(): bool
    {
        return false;
    }

    protected function findTrashed(): Model
    {
        $modelClass = $this->userModelClass();

        // The configured model is soft deletable, a scope PHPStan cannot see through the shared
        // `Model` base.
        /** @phpstan-ignore method.notFound */
        return $modelClass::query()->onlyTrashed()
            ->findOrFail((int) $this->route('trashedUser'));
    }
}
