<?php

namespace App\Services;

use App\Models\DeliverySchedule;

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
}
