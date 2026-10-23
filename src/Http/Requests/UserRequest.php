<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;
use Basics13\Models\UserType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Exists;
use Basics13\Concerns\ChecksPermissions;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;
use Basics13\Concerns\ProfileValidationRules;
use Basics13\Concerns\DelegatesUserManagement;
use Basics13\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;

class UserRequest extends FormRequest
{
    use ChecksPermissions;
    use DelegatesUserManagement;
    use PasswordValidationRules;
    use ProfileValidationRules;
    use ResolvesUserModel;

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function authorize(): bool
    {
        $record = $this->route('user');

        if ($record instanceof Model) {
            return $this->user()?->can('update', $record) ?? false;
        }

        return $this->user()?->can('create', $this->userModelClass()) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|Password|Exists|In|array<mixed>|string>>
     */
    public function rules(): array
    {
        $rules = [
            ...$this->profileRules($this->editingId()),
            'password' => $this->passwordRule(),
            // The role selector sends `assign_roles` along with the checkboxes, so an empty
            // selection is told apart from a form that does not show the selector at all.
            'assign_roles' => $this->assignsRoles() ? ['sometimes', 'boolean'] : ['prohibited'],
            'role_ids' => $this->assignsRoles() ? ['sometimes', 'array'] : ['prohibited'],
            'role_ids.*' => ['integer', 'distinct', $this->assignableRoleRule()],
            // A type admin always sends the types: the account has to keep one of its own.
            'assign_types' => $this->assignsTypes()
                ? [$this->delegatesTypes() ? 'required' : 'sometimes', 'boolean']
                : ['prohibited'],
            'user_type_ids' => $this->assignsTypes()
                ? ($this->delegatesTypes() ? ['required', 'array', 'min:1'] : ['sometimes', 'array'])
                : ['prohibited'],
            'user_type_ids.*' => ['integer', 'distinct', $this->assignableTypeRule()],
            'admin_type_ids' => $this->assignsTypes() && ! $this->delegatesTypes() ? ['sometimes', 'array'] : ['prohibited'],
            'admin_type_ids.*' => ['integer', 'distinct', Rule::in((array) $this->input('user_type_ids', []))],
        ];

        // Only the account holder changes its sign-in: someone else setting the password or the
        // email would take the account over.
        $record = $this->route('user');

        if ($record instanceof Model && ! $this->editsOwnAccount()) {
            $rules['password'] = ['prohibited'];
            $rules['email'][] = Rule::in([$record->getAttribute('email')]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.prohibited' => __('basics13::messages.own_account_only'),
            'email.in' => __('basics13::messages.own_account_only'),
            'assign_roles.prohibited' => __('You cannot assign roles to this account.'),
            'role_ids.prohibited' => __('You cannot assign roles to this account.'),
            'user_type_ids.prohibited' => __('You cannot assign types to this account.'),
            'admin_type_ids.prohibited' => __('You cannot assign types to this account.'),
        ];
    }

    /**
     * The roles the account ends up with, or null when the form does not change them. System roles
     * are assigned and removed only by the super-admin: for anybody else the ones the account
     * already holds are kept, since the form never offered them.
     *
     * @return list<int>|null
     */
    public function roleIds(): ?array
    {
        if (! $this->boolean('assign_roles')) {
            return null;
        }

        $roleIds = array_map(intval(...), (array) $this->validated('role_ids', []));
        $record = $this->route('user');
        $user = $this->user();

        if ($record instanceof Model && ($user === null || ! $this->isSuperAdmin($user))) {
            $roleIds = [...$roleIds, ...$this->heldRoleIds($record, systemOnly: true)];
        }

        // A type admin is never offered the roles it could not grant, so the ones the account holds
        // are kept the same way.
        if ($record instanceof Model && $user !== null && ! $this->permits($user, 'roles.manage')) {
            $roleIds = [...$roleIds, ...array_diff($this->heldRoleIds($record), $this->grantableRoleIds($user))];
        }

        return array_values(array_unique($roleIds));
    }

    /**
     * The types the account ends up with, each with its admin flag, or null when the form does not
     * change them. A type admin only adds or removes the types it administers, never the admin
     * flag: every other type the account holds stays as it is.
     *
     * @return array<int, array{is_admin: bool}>|null
     */
    public function types(): ?array
    {
        $user = $this->user();

        if ($user === null || ! $this->assignsTypes() || ! $this->boolean('assign_types')) {
            return null;
        }

        $chosen = array_map(intval(...), (array) $this->validated('user_type_ids', []));

        if (! $this->delegatesTypes()) {
            $admins = array_map(intval(...), (array) $this->validated('admin_type_ids', []));

            return array_combine($chosen, array_map(
                static fn (int $id): array => ['is_admin' => in_array($id, $admins, true)],
                $chosen,
            ));
        }

        $record = $this->route('user');
        $held = $record instanceof Model ? $this->heldTypes($record) : [];
        $administered = $this->administeredTypeIds($user);
        $types = [];

        foreach ($held as $id => $isAdmin) {
            if (! in_array($id, $administered, true)) {
                $types[$id] = ['is_admin' => $isAdmin];
            }
        }

        foreach ($chosen as $id) {
            $types[$id] = ['is_admin' => $held[$id] ?? false];
        }

        return $types;
    }

    /**
     * Assigning roles needs `roles.manage`, or `users.manage_own` for a type admin, and is never
     * done on one's own account.
     */
    private function assignsRoles(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($this->permits($user, 'roles.manage') || $this->managesAsTypeAdmin($user))
            && ! $this->editsOwnAccount();
    }

    /**
     * Assigning types needs `users.edit`, or `users.manage_own` for a type admin.
     */
    private function assignsTypes(): bool
    {
        $user = $this->user();

        return $user !== null && ($this->permits($user, 'users.edit') || $this->managesAsTypeAdmin($user));
    }

    /**
     * Whether the types are assigned as a type admin: only its own types, never the admin flag.
     */
    private function delegatesTypes(): bool
    {
        $user = $this->user();

        return $user !== null && ! $this->permits($user, 'users.edit');
    }

    /**
     * A type outside the trash, active or already held by the account so an edit can keep it after
     * it was archived. Only the types it administers for a type admin.
     */
    private function assignableTypeRule(): Exists|In
    {
        $user = $this->user();

        if ($user !== null && $this->delegatesTypes()) {
            return Rule::in($this->administeredTypeIds($user));
        }

        $record = $this->route('user');
        $held = $record instanceof Model ? array_keys($this->heldTypes($record)) : [];

        return Rule::exists(UserType::class, 'id')
            ->whereNull('deleted_at')
            ->where(static function (Builder $query) use ($held): void {
                $query->where('active', true);

                if ($held !== []) {
                    $query->orWhereIn('id', $held);
                }
            });
    }

    /**
     * The types the account holds, with the admin flag of each.
     *
     * @return array<int, bool>
     */
    private function heldTypes(Model $record): array
    {
        if (! method_exists($record, 'userTypes')) {
            return [];
        }

        $types = [];

        foreach ($record->userTypes()->get() as $type) {
            $types[(int) $type->getKey()] = (bool) $type->getRelationValue('pivot')?->getAttribute('is_admin');
        }

        return $types;
    }

    private function editsOwnAccount(): bool
    {
        $record = $this->route('user');

        return $record instanceof Model && (int) $record->getKey() === (int) $this->user()?->getAuthIdentifier();
    }

    /**
     * A role outside the trash, active or already held by the account so an edit can keep it after
     * it was archived. A system role only for the super-admin; for a type admin, only the roles it
     * could grant.
     */
    private function assignableRoleRule(): Exists
    {
        $record = $this->route('user');
        $held = $record instanceof Model ? $this->heldRoleIds($record) : [];
        $user = $this->user();
        $withSystemRoles = $user !== null && $this->isSuperAdmin($user);

        // A type admin grants only the roles whose every permission it holds itself.
        $grantable = $user !== null && ! $this->permits($user, 'roles.manage') ? $this->grantableRoleIds($user) : null;

        return Rule::exists(Role::class, 'id')
            ->whereNull('deleted_at')
            ->when(! $withSystemRoles, static fn (Exists $rule): Exists => $rule->where('is_system', false))
            ->when($grantable !== null, static fn (Exists $rule): Exists => $rule->whereIn('id', $grantable))
            ->where(static function (Builder $query) use ($held): void {
                $query->where('active', true);

                if ($held !== []) {
                    $query->orWhereIn('id', $held);
                }
            });
    }

    /**
     * @return list<int>
     */
    private function heldRoleIds(Model $record, bool $systemOnly = false): array
    {
        if (! method_exists($record, 'roles')) {
            return [];
        }

        return array_values(array_map(intval(...), $record->roles()
            ->when($systemOnly, static fn ($query) => $query->where('is_system', true))
            ->pluck('BAS_roles.id')
            ->all()));
    }

    /**
     * The account being edited, so the email rule ignores its own row. Null while creating.
     */
    private function editingId(): ?int
    {
        $record = $this->route('user');

        return $record instanceof Model ? (int) $record->getKey() : null;
    }

    /**
     * The password is required when the account is created and optional when it is edited: a form
     * that leaves it out keeps the current one. The confirmation rule of the shared trait stays.
     *
     * @return array<int, ValidationRule|Password|array<mixed>|string>
     */
    private function passwordRule(): array
    {
        $rules = $this->passwordRules();

        if ($this->route('user') instanceof Model) {
            $rules[0] = 'nullable';
        }

        return $rules;
    }
}
