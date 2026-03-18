<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use Illuminate\Support\Facades\Log;

class DeliveryScheduleService
{
    public function generateScheduleNumber(): string
    {
        return static::generateStaticScheduleNumber();
    }

    public static function generateStaticScheduleNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'SCH-' . $date . '-';

        $max = DeliverySchedule::withoutGlobalScopes()
            ->where('schedule_number', 'like', $prefix . '%')
            ->max('schedule_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * K3: When delivery schedule is delivered, mark all related DOs to completed.
     *
     * Transition strategy:
     * - approved/request_approve/draft/request_stock => sent => completed
     * - sent/received/partial => completed
     */
    public function completeRelatedDeliveryOrders(DeliverySchedule $schedule): int
    {
        $schedule->loadMissing('suratJalans.deliveryOrder');

        $deliveryOrders = $schedule->suratJalans
            ->flatMap(fn ($sj) => $sj->deliveryOrder)
            ->unique('id')
            ->values();

        $completedCount = 0;

        foreach ($deliveryOrders as $deliveryOrder) {
            if (!$deliveryOrder instanceof DeliveryOrder) {
                continue;
            }

            if (in_array($deliveryOrder->status, ['completed', 'closed'])) {
                continue;
            }

            try {
                if (in_array($deliveryOrder->status, ['approved', 'request_approve', 'draft', 'request_stock'])) {
                    $deliveryOrder->update(['status' => 'sent']);
                    $deliveryOrder->refresh();
                }

                if (in_array($deliveryOrder->status, ['sent', 'received', 'partial', 'approved', 'request_stock'])) {
                    $deliveryOrder->update(['status' => 'completed']);
                    $completedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('DeliveryScheduleService: failed to complete related DO', [
                    'delivery_schedule_id' => $schedule->id,
                    'delivery_order_id' => $deliveryOrder->id,
                    'do_number' => $deliveryOrder->do_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $completedCount;
    }
}
