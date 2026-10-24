<?php

use Illuminate\Support\Facades\Route;
use Basics13\Http\Controllers\EmailTemplateController;
use Basics13\Http\Controllers\EmailConfigurationController;
use Basics13\Http\Controllers\EmailTemplateTrashController;
use Basics13\Http\Controllers\EmailTemplateArchivedController;
use Basics13\Http\Controllers\EmailConfigurationTrashController;
use Basics13\Http\Controllers\EmailConfigurationArchivedController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('email-configurations/archived', [EmailConfigurationArchivedController::class, 'index'])->name('email-configurations.archived.index');
    Route::patch('email-configurations/archived/{email_configuration}', [EmailConfigurationArchivedController::class, 'activate'])->whereNumber('email_configuration')->name('email-configurations.archived.activate');
    Route::patch('email-configurations/{email_configuration}/archive', [EmailConfigurationArchivedController::class, 'archive'])->whereNumber('email_configuration')->name('email-configurations.archive');
    Route::get('email-configurations/trash', [EmailConfigurationTrashController::class, 'index'])->name('email-configurations.trash.index');
    Route::patch('email-configurations/trash/{email_configuration}', [EmailConfigurationTrashController::class, 'restore'])->whereNumber('email_configuration')->name('email-configurations.trash.restore');
    Route::delete('email-configurations/trash/{email_configuration}', [EmailConfigurationTrashController::class, 'destroy'])->whereNumber('email_configuration')->name('email-configurations.trash.destroy');
    Route::post('email-configurations/{email_configuration}/send-test', [EmailConfigurationController::class, 'sendTest'])->whereNumber('email_configuration')->name('email-configurations.send-test');
    Route::resource('email-configurations', EmailConfigurationController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('email-templates/archived', [EmailTemplateArchivedController::class, 'index'])->name('email-templates.archived.index');
    Route::patch('email-templates/archived/{email_template}', [EmailTemplateArchivedController::class, 'activate'])->whereNumber('email_template')->name('email-templates.archived.activate');
    Route::patch('email-templates/{email_template}/archive', [EmailTemplateArchivedController::class, 'archive'])->whereNumber('email_template')->name('email-templates.archive');
    Route::get('email-templates/trash', [EmailTemplateTrashController::class, 'index'])->name('email-templates.trash.index');
    Route::patch('email-templates/trash/{email_template}', [EmailTemplateTrashController::class, 'restore'])->whereNumber('email_template')->name('email-templates.trash.restore');
    Route::delete('email-templates/trash/{email_template}', [EmailTemplateTrashController::class, 'destroy'])->whereNumber('email_template')->name('email-templates.trash.destroy');
    Route::post('email-templates/{email_template}/send-test', [EmailTemplateController::class, 'sendTest'])->whereNumber('email_template')->name('email-templates.send-test');
    Route::resource('email-templates', EmailTemplateController::class)->only(['index', 'store', 'update', 'destroy']);
});
