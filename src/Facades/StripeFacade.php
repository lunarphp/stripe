<?php

namespace GetCandy\Stripe\Facades;

use GetCandy\Base\PricingManagerInterface;
use Illuminate\Support\Facades\Facade;

class StripeFacade extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return 'gc:stripe';
    }
}
