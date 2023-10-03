<?php

namespace Lunar\Stripe;

use Lunar\Base\DataTransferObjects\PaymentAuthorize;
use Lunar\Base\DataTransferObjects\PaymentCapture;
use Lunar\Base\DataTransferObjects\PaymentRefund;
use Lunar\Exceptions\DisallowMultipleCartOrdersException;
use Lunar\Models\Transaction;
use Lunar\PaymentTypes\AbstractPayment;
use Lunar\Stripe\Actions\UpdateOrderFromIntent;
use Lunar\Stripe\Facades\StripeFacade;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripePaymentType extends AbstractPayment
{
    /**
     * The Stripe instance.
     *
     * @var \Stripe\StripeClient
     */
    protected $stripe;

    /**
     * The Payment intent.
     */
    protected PaymentIntent $paymentIntent;

    /**
     * The policy when capturing payments.
     *
     * @var string
     */
    protected $policy;

    /**
     * Initialise the payment type.
     */
    public function __construct()
    {
        $this->stripe = StripeFacade::getClient();

        $this->policy = config('lunar.stripe.policy', 'automatic');
    }

    /**
     * Authorize the payment for processing.
     */
    final public function authorize(): PaymentAuthorize
    {
        if (! $this->order || ! $this->order = $this->cart->draftOrder) {
            try {
                $this->order = $this->cart->createOrder();
            } catch (DisallowMultipleCartOrdersException $e) {
                return new PaymentAuthorize(
                    success: false,
                    message: $e->getMessage(),
                );
            }
        }

        if ($this->order->placed_at) {
            return new PaymentAuthorize(
                success: false,
                message: 'This order has already been placed',
                orderId: $this->order->id,
            );
        }

        $this->paymentIntent = $this->stripe->paymentIntents->retrieve(
            $this->data['payment_intent']
        );

        if (! $this->paymentIntent) {
            return new PaymentAuthorize(
                success: false,
                message: 'Unable to locate payment intent',
                orderId: $this->order->id,
            );
        }

        if ($this->paymentIntent->status == PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD) {
            return new PaymentAuthorize(
                success: false,
                message: 'A payment method is required for this intent.',
                orderId: $this->order->id,
            );
        }

        if ($this->paymentIntent->status == PaymentIntent::STATUS_REQUIRES_CAPTURE && $this->policy == 'automatic') {
            $this->paymentIntent = $this->stripe->paymentIntents->capture(
                $this->data['payment_intent']
            );
        }

        if ($this->cart) {
            if (! ($this->cart->meta['payment_intent'] ?? null)) {
                $this->cart->update([
                    'meta' => [
                        'payment_intent' => $this->paymentIntent->id,
                    ],
                ]);
            } else {
                $this->cart->meta['payment_intent'] = $this->paymentIntent->id;
                $this->cart->save();
            }
        }

        $order = (new UpdateOrderFromIntent)->execute($this->order, $this->paymentIntent);

        return new PaymentAuthorize(
            success: (bool) $order->placed_at,
            message: $this->paymentIntent->last_payment_error,
            orderId: $order->id
        );
    }

    /**
     * Capture a payment for a transaction.
     *
     * @param  int  $amount
     */
    public function capture(Transaction $transaction, $amount = 0): PaymentCapture
    {
        $payload = [];

        if ($amount > 0) {
            $payload['amount_to_capture'] = $amount;
        }

        try {
            $response = $this->stripe->paymentIntents->capture(
                $transaction->reference,
                $payload
            );
        } catch (InvalidRequestException $e) {
            return new PaymentCapture(
                success: false,
                message: $e->getMessage()
            );
        }

        $charges = $response->charges->data;

        $transactions = [];

        foreach ($charges as $charge) {
            $card = $charge->payment_method_details->card;
            $transactions[] = [
                'parent_transaction_id' => $transaction->id,
                'success' => $charge->status != 'failed',
                'type' => 'capture',
                'driver' => 'stripe',
                'amount' => $charge->amount_captured,
                'reference' => $response->id,
                'status' => $charge->status,
                'notes' => $charge->failure_message,
                'card_type' => $card->brand,
                'last_four' => $card->last4,
                'captured_at' => $charge->amount_captured ? now() : null,
            ];
        }

        $transaction->order->transactions()->createMany($transactions);

        return new PaymentCapture(success: true);
    }

    /**
     * Refund a captured transaction
     *
     * @param  string|null  $notes
     */
    public function refund(Transaction $transaction, int $amount = 0, $notes = null): PaymentRefund
    {
        try {
            $refund = $this->stripe->refunds->create(
                ['payment_intent' => $transaction->reference, 'amount' => $amount]
            );
        } catch (InvalidRequestException $e) {
            return new PaymentRefund(
                success: false,
                message: $e->getMessage()
            );
        }

        $transaction->order->transactions()->create([
            'success' => $refund->status != 'failed',
            'type' => 'refund',
            'driver' => 'stripe',
            'amount' => $refund->amount,
            'reference' => $refund->payment_intent,
            'status' => $refund->status,
            'notes' => $notes,
            'card_type' => $transaction->card_type,
            'last_four' => $transaction->last_four,
        ]);

        return new PaymentRefund(
            success: true
        );
    }
}
