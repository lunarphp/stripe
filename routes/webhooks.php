<?php

use Illuminate\Support\Facades\Route;

Route::post(config('lunar.stripe.webhook_path', 'stripe/webhook'), \Lunar\Stripe\Http\Controllers\WebhookController::class)
    ->middleware([\Lunar\Stripe\Http\Middleware\StripeWebhookMiddleware::class, 'api'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])

    ->name('lunar.stripe.webhook');
