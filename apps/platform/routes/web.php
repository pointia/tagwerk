<?php

use App\Http\Controllers\AdminHouseholdController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\HouseholdArchiveController;
use App\Http\Controllers\LoginLinkController;
use App\Http\Controllers\PhotoPreviewController;
use App\Http\Controllers\SupportAccessController;
use App\Http\Middleware\RestrictToAllowedNetworks;
use Illuminate\Support\Facades\Route;

// The admin panel's sign-in link from the mail; a guest follows it.
Route::get('/login-link', LoginLinkController::class)
    ->middleware([RestrictToAllowedNetworks::class, 'mail', 'throttle:login'])
    ->name('login-link');

// A household's export from the mail; the signature is the key, it may open on another device.
Route::get('/export/{household}/{file}', HouseholdArchiveController::class)
    ->whereNumber('household')
    ->where('file', '[0-9a-z]{26}')
    ->middleware('signed')
    ->name('household.archive');

// Admin-panel routes: the same networks as the panel itself.
Route::middleware([RestrictToAllowedNetworks::class, 'auth', 'household'])->group(function (): void {
    Route::get('/photos/{photo}', PhotoPreviewController::class)
        ->name('photo.preview')
        ->withTrashed();

    Route::get('/auth/{area}/{provider}/redirect', [ConnectionController::class, 'redirect'])
        ->name('connection.connect');

    Route::get('/auth/{area}/{provider}/callback', [ConnectionController::class, 'callback'])
        ->name('connection.callback');

    Route::post('/support/enter', [SupportAccessController::class, 'enter'])->name('support.enter');
    Route::post('/support/leave', [SupportAccessController::class, 'leave'])->name('support.leave');
    Route::post('/household', AdminHouseholdController::class)->name('household.switch');
});
