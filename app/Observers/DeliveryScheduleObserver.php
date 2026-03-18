<?php

namespace App\Observers;

use App\Models\DeliverySchedule;
use App\Services\DeliveryScheduleService;

class DeliveryScheduleObserver
{
    public function updated(DeliverySchedule $schedule): void
    {
        $originalStatus = $schedule->getOriginal('status');
        if ($originalStatus !== 'delivered' && $schedule->status === 'delivered') {
            app(DeliveryScheduleService::class)->completeRelatedDeliveryOrders($schedule);
        }
    }
}
