<?php

namespace Lunar\Stripe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Lunar\Events\PaymentAttemptEvent;
use Lunar\Facades\Payments;
use Lunar\Models\Cart;
use Lunar\Stripe\Concerns\ConstructsWebhookEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;

final class WebhookController extends Controller
{
    public function __invoke(Request $request): Response
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
            Log::error($e->getMessage());

            return response(status: 400);
        }

        $paymentIntent = $event->data->object->id;

        $cart = Cart::where('meta->payment_intent', '=', $paymentIntent)->first();

        if (! $cart) {
            Log::error("Unable to find cart with intent ${paymentIntent}");

            return response(status: 400);
        }

        $payment = Payments::driver('stripe')->cart($cart->calculate())->withData([
            'payment_intent' => $paymentIntent,
        ])->authorize();

        PaymentAttemptEvent::dispatch($payment);

        return response(status: 200);
    }
}
