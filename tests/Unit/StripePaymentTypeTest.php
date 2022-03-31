<?php

namespace Tests\Unit;

use GetCandy\DataTypes\Price;
use GetCandy\DataTypes\ShippingOption;
use GetCandy\Facades\ShippingManifest;
use GetCandy\Models\Cart;
use GetCandy\Models\CartAddress;
use GetCandy\Models\CartLine;
use GetCandy\Models\Currency;
use GetCandy\Models\ProductVariant;
use GetCandy\Models\TaxClass;
use GetCandy\Models\Transaction;
use GetCandy\Stripe\Facades\StripeFacade;
use GetCandy\Stripe\Managers\StripeManager;
use GetCandy\Stripe\StripePaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test we can release a payment with Stripe.
     * @group noo
     * @return void
     */
    public function test_an_order_is_captured()
    {
        $currency = Currency::factory()->create([
            'default' => true,
        ]);

        $taxClass = TaxClass::factory()->create();

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
        ]);

        ShippingManifest::addOption(
            new ShippingOption(
                description: 'Basic Delivery',
                identifier: 'BASDEL',
                price: new Price(500, $cart->currency, 1),
                taxClass: $taxClass
            )
        );

        CartAddress::factory()->create([
            'cart_id' => $cart->id,
            'shipping_option' => 'BASDEL',
        ]);

        CartAddress::factory()->create([
            'cart_id' => $cart->id,
            'type' => 'billing',
        ]);

        ProductVariant::factory()->create()->each(function ($variant) use ($currency) {
            $variant->prices()->create([
                'price' => 1.99,
                'currency_id' => $currency->id,
            ]);
        });

        CartLine::factory()->create([
            'cart_id' => $cart->id,
        ]);

        $payment = new StripePaymentType;

        $payment->cart($cart)->withData([
            'payment_intent' => 'PI_CAPTURE',
        ])->release();

        $this->assertEquals('PI_CAPTURE', $cart->meta->payment_intent);

        $this->assertDatabaseHas((new Transaction)->getTable(), [
            'order_id' => $cart->refresh()->order->id,
            'type' => 'capture',
        ]);
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_existing_intent_is_returned_if_it_exists()
    {
        $currency = Currency::factory()->create([
            'default' => true,
        ]);

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'meta' => [
                'payment_intent' => 'INTENT-123',
            ],
        ]);

        CartAddress::factory()->create([
            'cart_id' => $cart->id,
        ]);

        ProductVariant::factory()->create()->each(function ($variant) use ($currency) {
            $variant->prices()->create([
                'price' => 1.99,
                'currency_id' => $currency->id,
            ]);
        });

        CartLine::factory()->create([
            'cart_id' => $cart->id,
        ]);

        $this->partialMock(StripeManager::class, function ($mock) {
            $intent = new stdClass;
            $intent->id = 'INTENT-123';

            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('fetchIntent')
                ->once()
                ->andReturn($intent);
        });

        StripeFacade::createIntent($cart->getManager()->getCart());

        $this->assertEquals(
            $cart->refresh()->meta->payment_intent,
            'INTENT-123'
        );
    }
}
