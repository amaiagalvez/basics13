<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Middleware\RequirePasswordForLivewire;

Route::middleware(['web', 'auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'basics13::pages.settings.profile')->name('profile.edit');
});

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'basics13::pages.settings.appearance')->name('appearance.edit');

    $securityRoute = Route::livewire('settings/security', 'basics13::pages.settings.security');

    if (config('basics13.auth.features.password_confirmation', true)) {
        $securityRoute->middleware(RequirePasswordForLivewire::class);
    }

    $securityRoute->name('security.edit');
});

if (config('basics13.auth.features.passkeys', true)) {
    Route::get('.well-known/passkey-endpoints', function () {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    })->name('well-known.passkeys');
}
