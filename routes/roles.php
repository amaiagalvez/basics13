<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\RoleController;
use Basics13\Http\Controllers\RoleTrashController;
use Basics13\Http\Controllers\PermissionController;
use Basics13\Http\Controllers\RoleArchivedController;
use Basics13\Http\Controllers\PermissionSummaryController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('roles/archived', [RoleArchivedController::class, 'index'])->name('roles.archived.index');
    Route::patch('roles/archived/{role}', [RoleArchivedController::class, 'activate'])->whereNumber('role')->name('roles.archived.activate');
    Route::patch('roles/{role}/archive', [RoleArchivedController::class, 'archive'])->whereNumber('role')->name('roles.archive');
    Route::get('roles/trash', [RoleTrashController::class, 'index'])->name('roles.trash.index');
    Route::patch('roles/trash/{role}', [RoleTrashController::class, 'restore'])->whereNumber('role')->name('roles.trash.restore');
    Route::delete('roles/trash/{role}', [RoleTrashController::class, 'destroy'])->whereNumber('role')->name('roles.trash.destroy');
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);

    // Read-only: the permissions are seeded by each package.
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

    Route::get('users/permissions-summary', [PermissionSummaryController::class, 'index'])->name('users.permissions-summary.index');
    Route::get('users/permissions-summary/export', [PermissionSummaryController::class, 'export'])->name('users.permissions-summary.export');
});
