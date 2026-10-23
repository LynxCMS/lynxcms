<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * Lynx serves every URL these routes leave free, the home page included, so the welcome route
 * Laravel starts with is gone. These are the app's own routes.
 */

// #region bookings
Route::post('/book/{session}', [BookingController::class, 'store'])
    ->whereNumber('session')->middleware('throttle:10,1')->name('bookings.store');
Route::get('/bookings/{uuid}', [BookingController::class, 'show'])
    ->whereUuid('uuid')->name('bookings.show');

// Stripe signs its webhooks instead of sending a CSRF token
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)->name('stripe.webhook');
// #endregion bookings

// #region account
Route::get('/account', AccountController::class)->middleware('auth')->name('account');
// #endregion account
