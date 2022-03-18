<?php

namespace GetCandy\Stripe\Managers;

use GetCandy\Models\Cart;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripeManager
{
    /**
     * The policy when working with payments.
     *
     * @var string
     */
    protected $policy;


    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.key'));
    }

    /**
     * Create a payment intent from a Cart
     *
     * @param Cart $cart
     * @return \Stripe\PaymentIntent
     */
    public function createIntent(Cart $cart)
    {
        $shipping = $cart->shippingAddress;

        $meta = $cart->meta;

        if ($meta && $meta->payment_intent) {
            $intent = $this->fetchIntent(
                $meta->payment_intent
            );

            if ($intent) {
                return $intent;
            }
        }

        $paymentIntent = PaymentIntent::create([
            'amount' => $cart->total->value,
            'currency' => $cart->currency->code,
            'payment_method_types' => ['card'],
            'capture_method' => config('getcandy.stripe.policy', 'automatic'),
            'shipping' => [
                'name' => "{$shipping->first_name} {$shipping->last_name}",
                'address' => [
                    'city' => $shipping->city,
                    'country' => $shipping->country->iso2,
                    'line1' => $shipping->line_one,
                    'line2' => $shipping->line_two,
                    'postal_code' => $shipping->postcode,
                    'state' => $shipping->state,
                ],
            ],
        ]);

        if (!$meta) {
            $cart->update([
                'meta' => [
                    'payment_intent' => $paymentIntent->id,
                ],
            ]);
        } else {
            $meta->payment_intent = $paymentIntent->id;
            $cart->meta = $meta;
            $cart->save();
        }

        return $paymentIntent;
    }

    /**
     * Fetch an intent from the Stripe API.
     *
     * @param string $intentId
     * @return null|\Stripe\PaymentIntent
     */
    public function fetchIntent($intentId)
    {
        try {
            $intent = PaymentIntent::retrieve($intentId);
        } catch (InvalidRequestException $e) {
            return null;
        }

        return $intent;
    }
}
