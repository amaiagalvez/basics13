<?php

namespace Basics13\Database\Factories;

use Basics13\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->unique()->word();

        return [
            'slug' => $module.'.view',
            'module' => $module,
        ];
    }

    /**
     * The ids of these permissions, creating the ones that are not seeded yet.
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    public static function idsFor(array $slugs): array
    {
        return array_map(
            static fn (string $slug): int => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug, 2)[0]],
            )->id,
            $slugs,
        );
    }
}
