<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\SessionLogController;

// Read-only: the register is written by the login audit and has no write route.
Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('sessions', [SessionLogController::class, 'index'])->name('sessions.index');
});
