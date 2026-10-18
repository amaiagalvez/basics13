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
