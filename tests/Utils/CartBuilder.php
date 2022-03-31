<?php

namespace Tests\Utils;

use GetCandy\DataTypes\Price;
use GetCandy\DataTypes\ShippingOption;
use GetCandy\Facades\ShippingManifest;
use GetCandy\Models\Cart;
use GetCandy\Models\CartAddress;
use GetCandy\Models\CartLine;
use GetCandy\Models\Currency;
use GetCandy\Models\Language;
use GetCandy\Models\ProductVariant;
use GetCandy\Models\TaxClass;

class CartBuilder
{
    public static function build(array $cartParams = [])
    {
        Language::factory()->create([
            'default' => true,
        ]);

        $currency = Currency::factory()->create([
            'default' => true,
        ]);

        $taxClass = TaxClass::factory()->create();

        $cart = Cart::factory()->create(array_merge([
            'currency_id' => $currency->id,
        ], $cartParams));

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

        return $cart;
    }
}
