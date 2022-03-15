<?php

namespace GetCandy\Stripe;

use GetCandy\Base\DataTransferObjects\PaymentRefund;
use GetCandy\Base\DataTransferObjects\PaymentRelease;
use GetCandy\Models\Transaction;
use GetCandy\PaymentTypes\AbstractPayment;
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

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.key'));
    }

    public function release(): PaymentRelease
    {
        if (!$this->order) {
            if (!$this->order = $this->cart->order) {
                $this->order = $this->cart->getManager()->createOrder();
            }
        }

        if ($this->order->placed_at) {
            // Somethings gone wrong!
            dd('Eep');
        }

        $this->paymentIntent = $this->stripe->paymentIntents->retrieve(
            $this->data['payment_intent']
        );

        if ($this->paymentIntent->status == 'requires_capture') {
            $this->paymentIntent = $this->stripe->paymentIntents->capture(
                $this->data['payment_intent']
            );
        }

        if (in_array($this->paymentIntent->status, ['success', 'processing'])) {
            return $this->releaseFailed();
        }

        return $this->releaseSuccess();
    }

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
            'refund' => true,
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
                return !$charge->refunded && $charge->status == 'succeeded';
            });

            $this->order->update([
                'status' => $this->config['released'] ?? 'paid',
                'placed_at' => now()->parse($successCharge->created),
            ]);

            $transactions = [];

            foreach ($charges as $charge) {
                $card = $charge->payment_method_details->card;

                $transactions[] = [
                    'success' => $charge->status != 'failed',
                    'refund' => !!$charge->amount_refunded,
                    'driver' => 'stripe',
                    'amount' => $charge->amount_captured ?: $charge->amount_refunded,
                    'reference' => $this->paymentIntent->id,
                    'status' => $charge->status,
                    'notes' => $charge->failure_message,
                    'card_type' => $card->brand,
                    'last_four' => $card->last4,
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
