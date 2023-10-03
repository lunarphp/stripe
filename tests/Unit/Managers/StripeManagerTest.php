<?php

use Lunar\Stripe\Facades\StripeFacade;
use Lunar\Stripe\Tests\Utils\CartBuilder;

uses(\Lunar\Stripe\Tests\Unit\TestCase::class);

it('can create a payment intent', function () {
    $cart = CartBuilder::build();

    StripeFacade::createIntent($cart->calculate());

    expect($cart->refresh()->meta['payment_intent'])->toBe('pi_1DqH152eZvKYlo2CFHYZuxkP');
});
