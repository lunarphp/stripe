<?php

namespace GetCandy\Stripe\Components;

use GetCandy\Models\Cart;
use GetCandy\Stripe\Facades\StripeFacade;
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

    /**
     * The return URL on a successful transaction
     *
     * @var string
     */
    public $returnUrl;

    /**
     * The policy for handling payments.
     *
     * @var string
     */
    public $policy;

    /**
     * {@inheritDoc}
     */
    protected $listeners = [
        'cardDetailsSubmitted',
    ];

    /**
     * {@inheritDoc}
     */
    public function mount()
    {
        Stripe::setApiKey(config('services.stripe.key'));
        $this->policy = config('stripe.policy', 'capture');
    }

    /**
     * Return the client secret for Payment Intent
     *
     * @return void
     */
    public function getClientSecretProperty()
    {
        $intent = StripeFacade::createIntent($this->cart);
        return $intent->client_secret;
    }

    /**
     * Return the carts billing address.
     *
     * @return void
     */
    public function getBillingProperty()
    {
        return $this->cart->billingAddress;
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        return view("getcandy::stripe.components.payment-form");
    }
}
