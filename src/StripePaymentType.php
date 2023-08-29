<?php

namespace Lunar\Stripe;

use Illuminate\Support\Facades\DB;
use Lunar\Base\DataTransferObjects\PaymentAuthorize;
use Lunar\Base\DataTransferObjects\PaymentCapture;
use Lunar\Base\DataTransferObjects\PaymentRefund;
use Lunar\Models\Transaction;
use Lunar\PaymentTypes\AbstractPayment;
use Lunar\Stripe\Facades\StripeFacade;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;

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
     *
     * @var PaymentIntent
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
     *
     * @return \Lunar\Base\DataTransferObjects\PaymentAuthorize
     */
    public function authorize(): PaymentAuthorize
    {
        if (! $this->order) {
            if (! $this->order = $this->cart->order) {
                $this->order = $this->cart->createOrder();
            }
        }

        if ($this->order->placed_at) {
            // Somethings gone wrong!
            return new PaymentAuthorize(
                success: false,
                message: 'This order has already been placed',
            );
        }

        $this->paymentIntent = $this->stripe->paymentIntents->retrieve(
            $this->data['payment_intent'],
            ['expand' => ['latest_charge']]
        );

        if ($this->paymentIntent->status == 'requires_capture' && $this->policy == 'automatic') {
            $this->paymentIntent = $this->stripe->paymentIntents->capture(
                $this->data['payment_intent'],
                ['expand' => ['latest_charge']]
            );
        }

        if ($this->cart) {
            if (! $this->cart->meta) {
                $this->cart->update([
                    'meta' => [
                        'payment_intent' => $this->paymentIntent->id,
                    ],
                ]);
            } else {
                $this->cart->meta->payment_intent = $this->paymentIntent->id;
                $this->cart->save();
            }
        }

        if (! in_array($this->paymentIntent->status, [
            'processing',
            'requires_capture',
            'succeeded',
        ])) {
            return new PaymentAuthorize(
                success: false,
                message: $this->paymentIntent->last_payment_error,
            );
        }

        return $this->releaseSuccess();
    }

    /**
     * Capture a payment for a transaction.
     *
     * @param  \Lunar\Models\Transaction  $transaction
     * @param  int  $amount
     * @return \Lunar\Base\DataTransferObjects\PaymentCapture
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
                array_merge($payload, ['expand' => ['latest_charge']])
            );
        } catch (InvalidRequestException $e) {
            return new PaymentCapture(
                success: false,
                message: $e->getMessage()
            );
        }

        $card = $response->latest_charge->payment_method_details->card;
        $transaction->order->transactions()->createMany([
            'parent_transaction_id' => $transaction->id,
            'success' => $response->latest_charge->status != 'failed',
            'type' => 'capture',
            'driver' => 'stripe',
            'amount' => $response->latest_charge->amount_captured,
            'reference' => $response->id,
            'status' => $response->latest_charge->status,
            'notes' => $response->latest_charge->failure_message,
            'card_type' => $card->brand,
            'last_four' => $card->last4,
            'captured_at' => $response->latest_charge->amount_captured ? now() : null,
        ]);

        return new PaymentCapture(success: true);
    }

    /**
     * Refund a captured transaction
     *
     * @param  \Lunar\Models\Transaction  $transaction
     * @param  int  $amount
     * @param  string|null  $notes
     * @return \Lunar\Base\DataTransferObjects\PaymentRefund
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

    /**
     * Return a successfully released payment.
     *
     * @return PaymentAuthorize
     */
    private function releaseSuccess()
    {
        DB::transaction(function () {
            $successCharge = $this->paymentIntent->latest_charge;

            $this->order->update([
                'status' => $this->config['released'] ?? 'paid',
                'placed_at' => now()->parse($successCharge->created),
            ]);

            $transactions = [];

            $type = 'capture';

            if ($this->policy == 'manual') {
                $type = 'intent';
            }

            $card = $successCharge->payment_method_details->card;
            $this->order->transactions()->create([
                'success' => $successCharge->status != 'failed',
                'type' => $successCharge->amount_refunded ? 'refund' : $type,
                'driver' => 'stripe',
                'amount' => $successCharge->amount,
                'reference' => $this->paymentIntent->id,
                'status' => $successCharge->status,
                'notes' => $successCharge->failure_message,
                'card_type' => $card->brand,
                'last_four' => $card->last4,
                'captured_at' => $successCharge->amount_captured ? now() : null,
                'meta' => [
                    'address_line1_check' => $card->checks->address_line1_check,
                    'address_postal_code_check' => $card->checks->address_postal_code_check,
                    'cvc_check' => $card->checks->cvc_check,
                ],
            ]);
        });

        return new PaymentAuthorize(success: true);
    }
}