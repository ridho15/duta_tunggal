<?php

namespace App\Traits;

use App\Observers\GlobalActivityObserver;

trait LogsGlobalActivity
{
    protected static function bootLogsGlobalActivity()
    {
        static::observe(GlobalActivityObserver::class);
    }
}
