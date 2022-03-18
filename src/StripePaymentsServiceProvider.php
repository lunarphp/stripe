<?php

namespace GetCandy\Stripe;

use GetCandy\Facades\Payments;
use GetCandy\Stripe\Components\PaymentForm;
use GetCandy\Stripe\Managers\StripeManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class StripePaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Register our payment type.
        Payments::extend('stripe', function ($app) {
            return $app->make(StripePaymentType::class);
        });

        $this->app->singleton('gc:stripe', function ($app) {
            return $app->make(StripeManager::class);
        });

        Blade::directive('stripeScripts', function () {
            return  <<<EOT
                <script src="https://js.stripe.com/v3/"></script>
            EOT;
        });

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gcstripe');

        $this->mergeConfigFrom(__DIR__."/../config/stripe.php", "stripe");

        // Register the stripe payment component.
        Livewire::component('stripe.payment', PaymentForm::class);
    }
}
