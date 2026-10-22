<?php

namespace Basics13\Database\Factories;

use Basics13\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    use HasStates;

    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(2);
        $name = is_array($words) ? implode(' ', $words) : $words;

        return [
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name),
            'description' => null,
        ];
    }

    /**
     * A system role: only the super-admin edits it, and it is never archived or deleted.
     */
    public function system(): static
    {
        return $this->afterMaking(static function (Role $role): void {
            $role->is_system = true;
        });
    }

    /**
     * Grant the role these permissions, creating the ones that are not seeded yet.
     *
     * @param  list<string>  $slugs
     */
    public function withPermissions(array $slugs): static
    {
        return $this->afterCreating(static function (Role $role) use ($slugs): void {
            $role->permissions()->syncWithoutDetaching(PermissionFactory::idsFor($slugs));
        });
    }
}
