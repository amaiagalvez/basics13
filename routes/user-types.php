<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\UserTypeController;
use Basics13\Http\Controllers\UserTypeTrashController;
use Basics13\Http\Controllers\UserTypeArchivedController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('user-types/archived', [UserTypeArchivedController::class, 'index'])->name('user-types.archived.index');
    Route::patch('user-types/archived/{user_type}', [UserTypeArchivedController::class, 'activate'])->whereNumber('user_type')->name('user-types.archived.activate');
    Route::patch('user-types/{user_type}/archive', [UserTypeArchivedController::class, 'archive'])->whereNumber('user_type')->name('user-types.archive');
    Route::get('user-types/trash', [UserTypeTrashController::class, 'index'])->name('user-types.trash.index');
    Route::patch('user-types/trash/{user_type}', [UserTypeTrashController::class, 'restore'])->whereNumber('user_type')->name('user-types.trash.restore');
    Route::delete('user-types/trash/{user_type}', [UserTypeTrashController::class, 'destroy'])->whereNumber('user_type')->name('user-types.trash.destroy');
    Route::get('user-types/options', [UserTypeController::class, 'selectOptions'])->name('user-types.options');
    Route::resource('user-types', UserTypeController::class)->only(['index', 'store', 'update', 'destroy']);
});
