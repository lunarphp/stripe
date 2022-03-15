<?php

namespace GetCandy\Stripe\Components;

use GetCandy\Models\Cart;
use Livewire\Component;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentForm extends Component
{
    /**
     * The instance of the order.
     *
     * @var Order
     */
    public Cart $cart;

    public $returnUrl;

    /**
     * {@inheritDoc}
     */
    public function mount()
    {
        Stripe::setApiKey(config('services.stripe.key'));
    }

    public function getClientSecretProperty()
    {
        $shipping = $this->cart->shippingAddress;

        $intent = PaymentIntent::create([
            'amount' => $this->cart->total->value,
            'currency' => $this->cart->currency->code,
            'payment_method_types' => ['card'],
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

        return $intent->client_secret;
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        return view('gcstripe::components.payment-form');
    }
}
