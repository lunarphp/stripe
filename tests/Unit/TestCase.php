<?php

namespace Lunar\Stripe\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Stripe\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }
}
