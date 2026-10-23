<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Controllers\UserController;
use Basics13\Http\Controllers\UserTrashController;
use Basics13\Http\Controllers\UserArchivedController;

$userModel = config('auth.providers.users.model');

if (! is_string($userModel) || ! is_subclass_of($userModel, Model::class)) {
    throw new LogicException('The configured users model must be an Eloquent model.');
}

// The account resource cannot lean on the implicit binding the other resources use: that needs a
// type-hinted controller signature, and the model is configured, not fixed. Binding `{user}`
// explicitly is what keeps the controller thin; the policy is registered for the same model in the
// service provider.
Route::model('user', $userModel);

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('users/archived', [UserArchivedController::class, 'index'])->name('users.archived.index');
    Route::patch('users/archived/{user}', [UserArchivedController::class, 'activate'])->whereNumber('user')->name('users.archived.activate');
    Route::patch('users/{user}/archive', [UserArchivedController::class, 'archive'])->whereNumber('user')->name('users.archive');

    Route::get('users/trash', [UserTrashController::class, 'index'])->name('users.trash.index');
    // The trash keeps its own parameter name: the `{user}` binding above resolves with the default
    // scope, which hides trashed records, so these two resolve the record from their requests.
    Route::patch('users/trash/{trashedUser}', [UserTrashController::class, 'restore'])->whereNumber('trashedUser')->name('users.trash.restore');
    Route::delete('users/trash/{trashedUser}', [UserTrashController::class, 'destroy'])->whereNumber('trashedUser')->name('users.trash.destroy');

    Route::get('users/options', [UserController::class, 'selectOptions'])->name('users.options');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

    // Impersonation routes
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->whereNumber('user')->name('users.impersonate');
    Route::post('impersonation/leave', [UserController::class, 'leaveImpersonation'])->name('impersonation.leave');
});
