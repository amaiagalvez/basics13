<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Basics13\Http\Requests\UserTypeRequest;
use Basics13\Http\Requests\UserTypeListRequest;
use Basics13\Queries\UserTypes\UserTypeListQuery;
use Basics13\Transformers\UserTypeListTransformer;
use Basics13\Http\Requests\UserTypeSelectOptionsRequest;
use Basics13\Support\Database\UniqueConstraintViolation;
use Basics13\Queries\UserTypes\UserTypeSelectOptionsQuery;

class UserTypeController extends Controller
{
    public function index(
        UserTypeListRequest $request,
        UserTypeListQuery $query,
        UserTypeListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $userTypes = $query->active($search);

        return $this->listView($request, 'basics13::user-types.list', [
            'userTypes' => $userTypes,
            'list' => $transformer->active(
                $userTypes,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $userTypes->total() : null),
            ),
            'scope' => 'active',
        ]);
    }

    public function selectOptions(
        UserTypeSelectOptionsRequest $request,
        UserTypeSelectOptionsQuery $query,
    ): JsonResponse {
        return response()->json([
            'results' => $query->search($request->search()),
        ]);
    }

    public function store(UserTypeRequest $request, UserTypeListQuery $query): RedirectResponse|JsonResponse
    {
        $name = $request->string('name_eu')->toString();
        $deletedType = $query->findTrashedByName($name);

        if ($deletedType !== null && ! $request->boolean('reuse_deleted_name')) {
            return $this->trashedTypeConflict($request, $deletedType);
        }

        try {
            $userType = UserType::create($request->validated());
        } catch (QueryException $exception) {
            $this->failOnDuplicatedName($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($userType->only(['id', 'name_eu']), 201);
        }

        return $this->backToList('user-types.index')->with('status', __('basics13::messages.created'));
    }

    public function update(UserTypeRequest $request, UserType $userType): RedirectResponse
    {
        try {
            $userType->update($request->validated());
        } catch (QueryException $exception) {
            $this->failOnDuplicatedName($exception);
        }

        return $this->backToList('user-types.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(UserType $userType): RedirectResponse
    {
        $this->authorize('delete', $userType);

        // The deleting hook holds the row lock and refuses the delete while an account holds the
        // type, so the controller only has to run it inside a transaction and report the outcome.
        $deleted = DB::transaction(static fn (): bool => $userType->delete() !== false);

        if (! $deleted) {
            return $this->backToList('user-types.index')
                ->with('error', __('basics13::messages.cannot_delete_related'));
        }

        return $this->backToList('user-types.index')->with('status', __('basics13::messages.moved_to_trash'));
    }

    /**
     * A trashed type already uses the euskera name the user is creating. The browser is sent back to
     * the list with the conflict modal open; a JSON caller gets a 409 it can render on the field.
     *
     * A type is named in four languages and the conflict modal offers to create it with the names
     * the trashed one had, so the whole set travels in the payload instead of the single `name` the
     * generic conflict of the base controller carries.
     */
    private function trashedTypeConflict(
        UserTypeRequest $request,
        UserType $trashedType,
    ): RedirectResponse|JsonResponse {
        $payload = [
            'id' => $trashedType->getKey(),
            ...$trashedType->only(['name_eu', 'name_es', 'name_fr', 'name_en']),
        ];

        if ($request->expectsJson()) {
            $message = __('basics13::messages.deleted_name_conflict', ['name' => $trashedType->name_eu]);

            return response()->json([
                'message' => $message,
                'errors' => ['name_eu' => [$message]],
            ], 409);
        }

        return $this->backToList('user-types.index')
            ->withInput()
            ->with('deleted_user_type_conflict', $payload);
    }

    /**
     * A write that passed validation and still hit a unique index lost a race against a concurrent
     * one, which the request cannot have seen. The index the database names is what tells which of
     * the four languages collided.
     */
    private function failOnDuplicatedName(QueryException $exception): never
    {
        UniqueConstraintViolation::rethrowAsValidationError($exception, $this->duplicatedNameField($exception));
    }

    /**
     * MySQL names the index of the colliding language (`..._active_name_es_unique`), while the
     * partial index SQLite and PostgreSQL build names the bare column (`...name_es`), so both
     * spellings are looked for.
     */
    private function duplicatedNameField(QueryException $exception): string
    {
        $message = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        foreach (UserType::NAME_FIELDS as $field) {
            if (str_contains($message, 'active_'.$field) || str_contains($message, '.'.$field)) {
                return $field;
            }
        }

        return 'name_eu';
    }
}
