<?php

namespace Tests;

use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    public static function prepare(): void
    {
        static::startChromeDriver();
    }
}
