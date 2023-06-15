<?php

namespace Lunar\Stripe\Actions;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Stripe\PaymentIntent;

class UpdateOrderFromIntent
{
    final public static function execute(
        Order $order,
        PaymentIntent $paymentIntent,
        string $successStatus = 'paid',
        string $failStatus = 'failed'
    ): Order {
        return DB::transaction(function () use ($order, $paymentIntent, $successStatus, $failStatus) {
            if ($order->placed_at) {
                return $order;
            }

            $charges = collect(
                $paymentIntent->charges->data
            );

            // First try and get a successful charge, otherwise get the first (latest) one.
            $charge = $charges->first(function ($charge) use ($paymentIntent) {
                return ! $charge->latest_charge == $charge->id;
            });

            $successful = (bool) !$charge->failure_code;

            $timestamp = now()->createFromTimestamp($charge->created);

            $order->update([
                'status' => $successful ? $successStatus : $failStatus,
                'placed_at' => $successful ? $timestamp : null,
            ]);

            $card = $charge->payment_method_details->card;

            $type = 'capture';

            if (!$charge->captured) {
                $type = 'intent';
            }

            $transaction = $order->transactions()->whereReference($paymentIntent->id)->first() ?: new Transaction([
                'order_id' => $order->id,
            ]);

            $transaction->fill([
                'success' => $successful,
                'type' => $charge->amount_refunded ? 'refund' : $type,
                'driver' => 'stripe',
                'amount' => $charge->amount,
                'reference' => $paymentIntent->id,
                'status' => $charge->status,
                'notes' => $charge->failure_message,
                'card_type' => $card->brand,
                'last_four' => $card->last4,
                'captured_at' => $charge->amount_captured ? $timestamp : null,
                'meta' => [
                    'address_line1_check' => $card->checks->address_line1_check,
                    'address_postal_code_check' => $card->checks->address_postal_code_check,
                    'cvc_check' => $card->checks->cvc_check,
                ]
            ]);

            $transaction->save();

            return $order;
        });
    }
}