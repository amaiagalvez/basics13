<?php

namespace Basics13\Http\Requests;

use Basics13\Models\Role;
use Illuminate\Support\Str;
use Basics13\Models\Permission;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    /**
     * Trim the name, and derive the slug from it when the form leaves the slug empty: the form
     * fills it in as the name is typed, so an empty one means nobody chose it.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $slug = $this->input('slug');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        if (is_string($slug) && trim($slug) !== '') {
            $this->merge(['slug' => trim($slug)]);
        } elseif (is_string($name)) {
            $this->merge(['slug' => Str::slug($name)]);
        }
    }

    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role
            ? ($this->user()?->can('update', $role) ?? false)
            : ($this->user()?->can('create', Role::class) ?? false);
    }

    /**
     * `is_system` and `active` have no rule, so whatever a payload sends for them never reaches
     * the model.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'min:2', 'max:'.MaxLength::string(), $this->uniqueAmongLiveRoles('name')],
            'slug' => [
                'required',
                'string',
                'max:'.MaxLength::string(),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->uniqueAmongLiveRoles('slug'),
                // The policies and the data migrations find a system role by its slug.
                ...($role instanceof Role && $role->is_system ? [Rule::in([$role->slug])] : []),
            ],
            'description' => ['nullable', 'string', 'max:'.MaxLength::string()],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists(Permission::class, 'id')],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('Use lowercase letters, numbers and single hyphens only.'),
            'slug.in' => __('The slug of a system role cannot change.'),
        ];
    }

    /**
     * The permissions the role ends up with. The form always posts the whole set of checkboxes,
     * so an absent field means a role without permissions.
     *
     * @return list<int>
     */
    public function permissionIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('permission_ids', [])));
    }

    /**
     * Unique among the roles outside the trash, archived ones included, as the index enforces it.
     */
    private function uniqueAmongLiveRoles(string $column): Unique
    {
        return Rule::unique(Role::class, $column)
            ->ignore($this->route('role'))
            ->whereNull('deleted_at');
    }
}
