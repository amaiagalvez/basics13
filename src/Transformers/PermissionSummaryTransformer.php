<?php

namespace Basics13\Transformers;

use Basics13\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Shapes an account into one summary row: its active roles and, per module, the permissions those
 * roles give it. `{module}.manage` is shown as full access to the module instead of its actions.
 */
class PermissionSummaryTransformer
{
    /**
     * @param  Collection<int, string>  $modules
     * @return array{id: mixed, name: string, email: string, roles: list<string>, modules: array<string, array{full: bool, permissions: list<string>}>}
     */
    public function row(Model $user, Collection $modules): array
    {
        /** @var Collection<int, Model> $roles */
        $roles = $user->relationLoaded('roles') ? $user->getRelation('roles') : collect();
        $slugs = $roles
            ->flatMap(static fn (Model $role): Collection => $role->getRelationValue('permissions')->pluck('slug'))
            ->unique();

        $byModule = [];

        foreach ($modules as $module) {
            $full = $slugs->contains($module.'.manage');

            $byModule[$module] = [
                'full' => $full,
                'permissions' => $full ? [] : array_values($slugs
                    ->filter(static fn (string $slug): bool => str_starts_with($slug, $module.'.'))
                    ->map(static fn (string $slug): string => __($slug))
                    ->sort()
                    ->all()),
            ];
        }

        return [
            'id' => $user->getKey(),
            'name' => (string) $user->getAttribute('name'),
            'email' => (string) $user->getAttribute('email'),
            'roles' => array_values($roles->map(static fn (Model $role): string => (string) $role->getAttribute('name'))->all()),
            'modules' => $byModule,
        ];
    }

    /**
     * The heading of each module column.
     *
     * @param  Collection<int, string>  $modules
     * @return array<string, string>
     */
    public function moduleLabels(Collection $modules): array
    {
        return $modules->mapWithKeys(static fn (string $module): array => [$module => Permission::moduleLabel($module)])->all();
    }

    /**
     * The CSV header and one line per row, the same columns the screen shows.
     *
     * @param  Collection<int, string>  $modules
     * @return list<string>
     */
    public function csvHeader(Collection $modules): array
    {
        return [__('Name'), __('Email'), __('Assigned roles'), ...array_values($this->moduleLabels($modules))];
    }

    /**
     * @param  array{id: mixed, name: string, email: string, roles: list<string>, modules: array<string, array{full: bool, permissions: list<string>}>}  $row
     * @return list<string>
     */
    public function csvLine(array $row): array
    {
        return array_map(self::csvCell(...), [
            $row['name'],
            $row['email'],
            implode('; ', $row['roles']),
            ...array_values(array_map(
                static fn (array $module): string => $module['full'] ? __('Full access') : implode('; ', $module['permissions']),
                $row['modules'],
            )),
        ]);
    }

    /**
     * Names are typed by users, and a spreadsheet runs a cell that starts like a formula: such a
     * cell is written as text.
     */
    private static function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
