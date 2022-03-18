# GetCandy Stripe

This addon enables Stripe payments on your GetCandy storefront.

## Requirements

- GetCandy >= `2.0-beta11`
- A [Stripe](http://stripe.com/) account with secret and public keys
- Laravel Livewire (if using frontend components)
- Alpinejs (if using frontend components)

## Installation

### Require the composer package

```sh
composer require getcandy/stripe
```

### Publish the configuration

This will publish the configuration under `config/getcandy/stripe.php`.

```sh
php artisan vendor:publish --tag=getcandy.stripe.config
```

### Publish the views (optional)

GetCandy Stripe comes with some helper components for you to use on your checkout, if you intend to edit the views they provide, you can publish them.

```sh
php artisan vendor:publish --tag=getcandy.stripe.components
```

### Enable the driver

Set the driver in `config/getcandy/payments.php`

```php
<?php

return [
    // ...
    'types' => [
        'card' => [
            // ...
            'driver' => 'stripe',
        ],
    ],
];
```

### Add your Stripe credentials

Make sure you have the Stripe credentials set in `config/services.php`

```php
'stripe' => [
    'key' => env('STRIPE_SECRET'),
    'public_key' => env('STRIPE_PK'),
],
```

> Keys can be found in your Stripe account https://dashboard.stripe.com/apikeys 

## Configuration

Below is a list of the available configuration options this package uses in `config/getcandy/stripe.php`

| Key | Default | Description |
| --- | --- | --- |
| `policy` | `automatic` | Determines the policy for taking payments and whether you wish to capture the payment manually later or take payment straight away. Available options `manual` or `automatic` |

---

# Backend Usage

## Creating a PaymentIntent

```php
use \GetCandy\Stripe\Facades\Stripe;

Stripe::createIntent(\GetCandy\Models\Cart $cart);
```

This method will create a Stripe PaymentIntent from a Cart and add the resulting ID to the meta for retrieval later. If a PaymentIntent already exists for a cart this will fetch it from Stripe and return that instead to avoid duplicate PaymentIntents being created.

```php
$cart->meta->payment_intent;
```

## Fetch an existing PaymentIntent

```php
use \GetCandy\Stripe\Facades\Stripe;

Stripe::fetchIntent($paymentIntentId);
```

# Storefront Usage

This addon provides some useful components you can use in your Storefront, they are built using Laravel Livewire and AlpineJs so bear that in mind.

If you are using the [Demo Store](https://github.com/getcandy/demo-store), this is already set up for you so you can refer to the source code to see what's happening.

## Set up the scripts

Place this in the `<head>` of your Storefront.

```blade
@stripeScripts
```

## Add the payment component

Wherever you want the payment form to appear, add this component:

```blade
@livewire('stripe.payment', [
  'cart' => $cart,
  'returnUrl' => route('checkout.view'),
])
```

The `returnUrl` is where we want to Stripe to redirect us when the payment has been processed on their servers. 

**Do NOT point this to the order confirmation page, as you'll see below**

## Process the result

You'll notice above we've told Stripe to redirect back to the checkout page, this is because although Stripe has either taken payment or allocated funds based on your policy, we still need GetCandy to process the result and create the transactions it needs against the order.

When Stripe redirects us we should have two parameters passed in the query string. `payment_intent_client_secret` and `payment_intent`. We can then check for these values and pass them off using GetCandy's Payments driver.

So, assuming we are using Livewire and on a `CheckoutPage` component (like on the Demo Store)

```php
if ($request->payment_intent) {
    $payment = \GetCandy\Facades\Payments::driver('card')->cart($cart)->withData([
        'payment_intent_client_secret' => $request->payment_intent_client_secret,
        'payment_intent' => $request->payment_intent,
    ])->release();
    
    if ($payment->success) {
        redirect()->route('checkout-success.view');
        return;
    }
}

```

And that should be it, you should then see the order in GetCandy with the correct Transactions.

If you have set your policy to `manual` you'll need to go into the Hub and manually capture the payment.
