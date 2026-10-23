<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\SessionLogController;
use Basics13\Http\Controllers\SessionDashboardController;

// Read-only: the register is written by the login audit and has no write route.
Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('sessions', [SessionLogController::class, 'index'])->name('sessions.index');
    Route::get('sessions/dashboard', SessionDashboardController::class)->name('sessions.dashboard');
});
