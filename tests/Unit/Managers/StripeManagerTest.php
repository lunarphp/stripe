<?php

namespace Tests\Unit\Managers;

use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\CartLine;
use Lunar\Models\Currency;
use Lunar\Models\ProductVariant;
use Lunar\Stripe\Facades\StripeFacade;
use Lunar\Stripe\Managers\StripeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\TestCase;
use Tests\Utils\CartBuilder;

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
        $cart = CartBuilder::build();

        StripeFacade::createIntent($cart->getManager()->getCart());

        $this->assertEquals(
            $cart->refresh()->meta->payment_intent,
            'pi_1DqH152eZvKYlo2CFHYZuxkP'
        );
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_existing_intent_is_returned_if_it_exists()
    {
        $cart = CartBuilder::build([
            'meta' => [
                'payment_intent' => 'PI_FOOBAR',
            ]
        ]);

        StripeFacade::createIntent($cart->getManager()->getCart());

        $this->assertEquals(
            $cart->refresh()->meta->payment_intent,
            'PI_FOOBAR'
        );
    }
}
