<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tagwerk\SocialLogin\SocialLoginController;

Route::middleware('api')->prefix('api')->group(function (): void {
    Route::get('/auth/social', [SocialLoginController::class, 'index']);
});

Route::middleware(['web', 'throttle:social'])->prefix('social')->group(function (): void {
    Route::get('/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
    Route::get('/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');
});
