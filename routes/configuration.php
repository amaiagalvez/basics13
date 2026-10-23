<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\SettingController;

// `/configuration`, because `settings/*` are the account settings.
Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('configuration', [SettingController::class, 'index'])->name('configuration.index');
    Route::put('configuration/{package}', [SettingController::class, 'update'])->name('configuration.update');
});
