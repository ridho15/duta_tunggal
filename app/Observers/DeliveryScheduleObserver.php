<?php

namespace App\Observers;

use App\Models\DeliverySchedule;
use App\Services\DeliveryScheduleService;

class DeliveryScheduleObserver
{
    public function updated(DeliverySchedule $schedule): void
    {
        $originalStatus = $schedule->getOriginal('status');

        // "Sebagian terkirim": barang sudah keluar gudang, jadi DO ikut berstatus sent (tidak completed
        // karena sebagian belum terkirim). Penyelesaian tetap hanya lewat status "delivered".
        if (! in_array($originalStatus, ['on_the_way', 'partial_delivered', 'delivered'], true)
            && in_array($schedule->status, ['on_the_way', 'partial_delivered'], true)) {
            app(DeliveryScheduleService::class)->startRelatedDeliveryOrders($schedule);
        }

        if ($originalStatus !== 'delivered' && $schedule->status === 'delivered') {
            app(DeliveryScheduleService::class)->completeRelatedDeliveryOrders($schedule);
        }
    }
}
