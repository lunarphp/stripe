<?php

namespace Lunar\Stripe\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Lunar\Stripe\Concerns\ConstructsWebhookEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;

class StripeWebhookMiddleware extends Middleware
{
    public function handle(Request $request, \Closure $next)
    {
        $secret = config('services.stripe.webhooks.payment_intent');
        $stripeSig = $request->header('Stripe-Signature');

        try {
            $event = app(ConstructsWebhookEvent::class)->constructEvent(
                $request->getContent(),
                $stripeSig,
                $secret
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            abort(400, $e->getMessage());
        }

        if (! in_array($event->type, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.payment_failed'])) {
            return response(status: 200);
        }

        return $next($request);
    }
}
