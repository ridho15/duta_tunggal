<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SequentialNumberGenerator
{
    /**
     * Generate sequential document number based on table, column, prefix, and date format.
     *
     * @param string $table Database table name
     * @param string $column Column name storing the document code
     * @param string $prefix Prefix (e.g., 'OR-', 'GRN-', 'PAY-REQ-', 'QC-P-')
     * @param int $digits Sequence padding length (default: 4 -> 0001)
     * @param string|null $dateFormat Date format to append to prefix (default: 'Ymd', or null for no date)
     * @param Carbon|null $date Specific date if applicable (defaults to now())
     * @return string Generated sequential document number
     */
    public static function generate(
        string $table,
        string $column,
        string $prefix,
        int $digits = 4,
        ?string $dateFormat = 'Ymd',
        ?Carbon $date = null
    ): string {
        $date = $date ?? now();
        $dateSegment = $dateFormat ? $date->format($dateFormat) . '-' : '';
        $fullPrefix = $prefix . $dateSegment;

        // Query matching existing records globally (bypassing model scopes & soft-deletes)
        $existingCodes = DB::table($table)
            ->where($column, 'like', $fullPrefix . '%')
            ->pluck($column);

        $maxSequence = 0;
        $prefixLen = strlen($fullPrefix);

        foreach ($existingCodes as $code) {
            $suffix = substr((string) $code, $prefixLen);
            if (preg_match('/^(\d+)/', $suffix, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxSequence) {
                    $maxSequence = $num;
                }
            }
        }

        $nextSequence = $maxSequence + 1;

        // Loop to guard against collision in concurrent environments
        do {
            $formattedNumber = str_pad((string) $nextSequence, $digits, '0', STR_PAD_LEFT);
            $candidate = $fullPrefix . $formattedNumber;
            $exists = DB::table($table)->where($column, $candidate)->exists();
            if ($exists) {
                $nextSequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
