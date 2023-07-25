<?php

namespace Lunar\Stripe\Managers;

use Lunar\Models\Cart;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeManager
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.key'));
        Stripe::setApiVersion('2022-08-01');
    }

    /**
     * Return the Stripe client
     *
     * @return void
     */
    public function getClient()
    {
        return new StripeClient([
            'api_key' => config('services.stripe.key'),
            'stripe_version' => '2022-08-01'
        ]);
    }

    /**
     * Create a payment intent from a Cart
     *
     * @param  Cart  $cart
     * @return \Stripe\PaymentIntent
     */
    public function createIntent(Cart $cart)
    {
        $shipping = $cart->shippingAddress;

        $meta = (array) $cart->meta;

        if ($meta && ! empty($meta['payment_intent'])) {
            $intent = $this->fetchIntent(
                $meta['payment_intent']
            );

            if ($intent) {
                return $intent;
            }
        }

        $paymentIntent = $this->buildIntent(
            $cart->total->value,
            $cart->currency->code,
            $shipping,
        );

        if (! $meta) {
            $cart->update([
                'meta' => [
                    'payment_intent' => $paymentIntent->id,
                ],
            ]);
        } else {
            $meta['payment_intent'] = $paymentIntent->id;
            $cart->meta = $meta;
            $cart->save();
        }

        return $paymentIntent;
    }

    /**
     * Fetch an intent from the Stripe API.
     *
     * @param  string  $intentId
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

    /**
     * Build the intent
     *
     * @param  int  $value
     * @param  string  $currencyCode
     * @param  \Lunar\Models\CartAddress  $shipping
     * @return \Stripe\PaymentIntent
     */
    protected function buildIntent($value, $currencyCode, $shipping)
    {
        return PaymentIntent::create([
            'amount' => $value,
            'currency' => $currencyCode,
            'payment_method_types' => ['card'],
            'capture_method' => config('lunar.stripe.policy', 'automatic'),
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
    }
}
