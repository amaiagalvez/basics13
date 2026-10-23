<?php

namespace Basics13\Queries\Settings;

use Basics13\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The configuration variables, one tab per package that seeds them. They are few, so a package's
 * variables are listed whole, in the order they were seeded.
 */
final class SettingListQuery
{
    /**
     * @return Collection<int, string>
     */
    public function packages(): Collection
    {
        return Setting::query()->distinct()->orderBy('package')->pluck('package');
    }

    /**
     * @return EloquentCollection<int, Setting>
     */
    public function forPackage(string $package): EloquentCollection
    {
        return Setting::query()->where('package', $package)->orderBy('id')->get();
    }
}
