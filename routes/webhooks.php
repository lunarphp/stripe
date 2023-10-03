<?php

use Illuminate\Support\Facades\Route;

Route::post(config('lunar.stripe.webhook_path', 'stripe/webhook'), \Lunar\Stripe\Http\Controllers\WebhookController::class)
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware(\Lunar\Stripe\Http\Middleware\StripeWebhookMiddleware::class)
    ->name('lunar.stripe.webhook');
