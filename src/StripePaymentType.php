<?php

namespace GetCandy\Stripe;

use GetCandy\Base\DataTransferObjects\PaymentCapture;
use GetCandy\Base\DataTransferObjects\PaymentRefund;
use GetCandy\Base\DataTransferObjects\PaymentRelease;
use GetCandy\Models\Transaction;
use GetCandy\PaymentTypes\AbstractPayment;
use GetCandy\Stripe\Facades\StripeFacade;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

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

        $this->policy = config('getcandy.stripe.policy', 'automatic');
    }

    /**
     * Release the payment for processing.
     *
     * @return \GetCandy\Base\DataTransferObjects\PaymentRelease
     */
    public function release(): PaymentRelease
    {
        if (!$this->order) {
            if (!$this->order = $this->cart->order) {
                $this->order = $this->cart->getManager()->createOrder();
            }
        }

        if ($this->order->placed_at) {
            // Somethings gone wrong!
            return new PaymentCapture(
                success: false,
                message: 'This order has already been placed',
            );
        }


        $this->paymentIntent = $this->stripe->paymentIntents->retrieve(
            $this->data['payment_intent']
        );

        if ($this->paymentIntent->status == 'requires_capture' && $this->policy == 'automatic') {
            $this->paymentIntent = $this->stripe->paymentIntents->capture(
                $this->data['payment_intent']
            );
        }

        if (in_array($this->paymentIntent->status, ['success', 'processing', 'requires_capture'])) {
            return $this->releaseFailed();
        }

        return $this->releaseSuccess();
    }

    /**
     * Capture a payment for a transaction.
     *
     * @param \GetCandy\Models\Transaction $transaction
     * @param integer $amount
     * @return \GetCandy\Base\DataTransferObjects\PaymentCapture
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
     * @param \GetCandy\Models\Transaction $transaction
     * @param integer $amount
     * @param string|null $notes
     * @return \GetCandy\Base\DataTransferObjects\PaymentRefund
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
     * @return void
     */
    private function releaseSuccess()
    {
        DB::transaction(function () {

            // Get our first successful charge.
            $charges = $this->paymentIntent->charges->data;

            $successCharge = collect($charges)->first(function ($charge) {
                return !$charge->refunded && ($charge->status == 'succeeded' || $charge->status == 'paid');
            });

            $this->order->update([
                'status' => $this->config['released'] ?? 'paid',
                'placed_at' => now()->parse($successCharge->created),
            ]);

            $transactions = [];

            $type = 'capture';

            if ($this->policy == 'manual') {
                $type = 'intent';
            }

            foreach ($charges as $charge) {
                $card = $charge->payment_method_details->card;
                $transactions[] = [
                    'success' => $charge->status != 'failed',
                    'type' => $charge->amount_refunded ? 'refund' : $type,
                    'driver' => 'stripe',
                    'amount' => $charge->amount,
                    'reference' => $this->paymentIntent->id,
                    'status' => $charge->status,
                    'notes' => $charge->failure_message,
                    'card_type' => $card->brand,
                    'last_four' => $card->last4,
                    'captured_at' => $charge->amount_captured ? now() : null,
                    'meta' => [
                        'address_line1_check' => $card->checks->address_line1_check,
                        'address_postal_code_check' => $card->checks->address_postal_code_check,
                        'cvc_check' => $card->checks->cvc_check,
                    ],
                ];
            }
            $this->order->transactions()->createMany($transactions);
        });

        return new PaymentRelease(success: true);
    }
}
