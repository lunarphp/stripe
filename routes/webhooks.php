<?php

use Illuminate\Support\Facades\Route;

Route::post('stripe/webhook', \Lunar\Stripe\Http\Controllers\WebhookController::class)
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
