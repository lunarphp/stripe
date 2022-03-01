<?php

namespace GetCandy\StripePayments;

use Illuminate\Support\ServiceProvider;

class StripePaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        dd('Hello');
    }
}
