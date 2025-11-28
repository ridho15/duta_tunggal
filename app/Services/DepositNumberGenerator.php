<?php

namespace App\Services;

use App\Models\Deposit;

class DepositNumberGenerator
{
    /**
     * Generate a deposit number in format: DEP-YYYYMMDD-XXXX
     * where XXXX is a zero-padded incremental counter for the day.
     *
     * This implementation looks up the latest deposit_number for today and
     * increments the numeric suffix. It is resilient to deleted rows (uses
     * the last suffix seen) and produces a 4-digit zero-padded counter.
     */
    public function generate(): string
    {
        $date = now()->format('Ymd');
        $prefix = "DEP-{$date}-";

        // Find the latest deposit_number for today
        $latest = Deposit::where('deposit_number', 'like', $prefix . '%')
            ->orderByDesc('created_at')
            ->value('deposit_number');

        if ($latest) {
            // Extract numeric suffix and increment
            $suffix = (int) str_replace($prefix, '', $latest);
            $next = $suffix + 1;
        } else {
            $next = 1;
        }

        // Ensure uniqueness by checking if the generated number already exists
        do {
            $suffixPadded = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $depositNumber = $prefix . $suffixPadded;
            $exists = Deposit::where('deposit_number', $depositNumber)->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $depositNumber;
    }
}
