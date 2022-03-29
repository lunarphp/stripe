<?php

namespace Tests\Unit;

use GetCandy\Models\Cart;
use GetCandy\Models\CartAddress;
use GetCandy\Models\CartLine;
use GetCandy\Models\Currency;
use GetCandy\Models\ProductVariant;
use GetCandy\Stripe\Facades\StripeFacade;
use GetCandy\Stripe\Managers\StripeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\TestCase;

class StripeManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_payment_intent_is_created()
    {
        $currency = Currency::factory()->create([
            'default' => true,
        ]);

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
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
            $intent->id = 'foobar';

            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('buildIntent')
                ->once()
                ->andReturn($intent);
        });

        StripeFacade::createIntent($cart->getManager()->getCart());

        $this->assertEquals(
            $cart->refresh()->meta->payment_intent,
            'foobar'
        );
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
