<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\SettingIndexRequest;
use Basics13\Queries\Settings\SettingListQuery;
use Basics13\Http\Requests\UpdateSettingsRequest;

/**
 * The application configuration: one tab per package with its variables. It is not a resource:
 * variables are seeded, never created, archived or deleted, and only their value is edited.
 */
class SettingController extends Controller
{
    public function index(SettingIndexRequest $request, SettingListQuery $query): View
    {
        $package = $request->package($query);
        $settings = $query->forPackage($package);
        $user = $request->user();

        return view('basics13::configuration.index', [
            'package' => $package,
            'settings' => $settings,
            'tabs' => $query->packages()->map(static fn (string $name): array => [
                'label' => __($name),
                'url' => route('configuration.index', ['package' => $name]),
                'current' => $name === $package,
                'count' => null,
                'test' => 'configuration-'.$name.'-tab',
            ])->all(),
            'canEdit' => $user !== null && $settings->every(static fn (Setting $setting): bool => $user->can('update', $setting)),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $package): RedirectResponse
    {
        DB::transaction(static function () use ($request): void {
            foreach ($request->settings() as $setting) {
                $setting->value = $request->valueFor($setting);
                $setting->save();
            }
        });

        return to_route('configuration.index', ['package' => $package])->with('status', __('basics13::messages.updated'));
    }
}
