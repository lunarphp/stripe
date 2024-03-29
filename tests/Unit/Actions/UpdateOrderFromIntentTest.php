<?php

uses(\Lunar\Stripe\Tests\Unit\TestCase::class);

it('creates pending transaction when status is requires_action', function () {

    $cart = \Lunar\Stripe\Tests\Utils\CartBuilder::build();

    $order = $cart->createOrder();

    $paymentIntent = \Lunar\Stripe\Facades\StripeFacade::getClient()
        ->paymentIntents
        ->retrieve('PI_REQUIRES_ACTION');

    $updatedOrder = \Lunar\Stripe\Actions\UpdateOrderFromIntent::execute($order, $paymentIntent);

    expect($updatedOrder->status)->toBe($order->status);
    expect($updatedOrder->placed_at)->toBeNull();
    expect($updatedOrder->refresh()->transactions)->toBeEmpty();
})->group('lunar.stripe.actions');
