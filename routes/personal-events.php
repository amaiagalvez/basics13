<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\PersonalEventController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::resource('personal-events', PersonalEventController::class)->only(['store', 'update', 'destroy']);
});
