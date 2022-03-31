<?php

namespace Tests\Unit;

use GetCandy\Base\DataTransferObjects\PaymentRelease;
use GetCandy\Models\Transaction;
use GetCandy\Stripe\Facades\StripeFacade;
use GetCandy\Stripe\StripePaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Utils\CartBuilder;

/**
 * @group stripe.payments
 */
class StripePaymentTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_is_captured()
    {
        $cart = CartBuilder::build();

        $payment = new StripePaymentType;

        $response = $payment->cart($cart)->withData([
            'payment_intent' => 'PI_CAPTURE',
        ])->release();

        $this->assertInstanceOf(PaymentRelease::class, $response);
        $this->assertTrue($response->success);
        $this->assertNotNull($cart->refresh()->order->placed_at);

        $this->assertEquals('PI_CAPTURE', $cart->meta->payment_intent);

        $this->assertDatabaseHas((new Transaction)->getTable(), [
            'order_id' => $cart->refresh()->order->id,
            'type' => 'capture',
        ]);
    }

    /**
     * @group thisone
     */
    public function test_handle_failed_payment()
    {
        $cart = CartBuilder::build();

        $payment = new StripePaymentType;

        $response = $payment->cart($cart)->withData([
            'payment_intent' => 'PI_FAIL',
        ])->release();

        $this->assertInstanceOf(PaymentRelease::class, $response);
        $this->assertFalse($response->success);
        $this->assertNull($cart->refresh()->order->placed_at);

        $this->assertDatabaseMissing((new Transaction)->getTable(), [
            'order_id' => $cart->refresh()->order->id,
            'type' => 'capture',
        ]);
    }

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
