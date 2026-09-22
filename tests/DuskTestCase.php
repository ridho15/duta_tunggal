<?php

namespace Tests;

use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (env('APP_ENV') === 'production') {
            self::markTestSkipped('Dusk tests skipped in production');
        }

        parent::setUpBeforeClass();
    }

    /**
     * Prepare for Dusk test execution.
     */
    public static function prepare(): void
    {
        static::startChromeDriver();
    }
}
