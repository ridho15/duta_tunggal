<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TaxService
{
    /**
     * Normalize tax type value across typos and variants.
     */
    public static function normalizeType(?string $type): string
    {
        $t = strtolower(trim((string) $type));
        if ($t === '' || $t === 'null') {
            if (function_exists('app') && app()->bound('log')) {
                Log::warning('TaxService: tipe_pajak is null/empty, defaulting to Eksklusif', [
                    'raw_type' => $type,
                    'trace'    => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                ]);
            }
            return 'Eksklusif'; // default system mode per business logic (amount + ppn)
        }
        return match ($t) {
            'inklusif', 'inclusive', 'ppn included', 'ppn_included' => 'Inklusif',
            'eksklusif', 'eklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'Eksklusif',
            'non pajak', 'non-pajak', 'nonpajak', 'none' => 'Non Pajak',
            default => ucfirst($t),
        };
    }

    /**
     * Compute DPP and PPN for an inclusive gross amount.
     * Example: gross 1,200,000, rate 12% => DPP = 1,200,000 * 100/112, PPN = DPP * 12%.
     */
    public static function computeFromInclusiveGross(float $gross, float $ratePercent): array
    {
        if ($ratePercent <= 0) {
            return ['dpp' => $gross, 'ppn' => 0.0, 'total' => $gross];
        }
        // Correct formula for inclusive tax (PPN included in gross):
        // DPP = gross * 100 / (100 + rate)
        // PPN = gross - DPP
        // Rounded to nearest Rupiah per PMK 136/2023
        $dpp = round($gross * (100.0 / (100.0 + $ratePercent)), 0);
        $ppn = round($gross - $dpp, 0);
        return ['dpp' => $dpp, 'ppn' => $ppn, 'total' => $gross];
    }

    /**
     * Compute DPP and PPN based on tax type.
     * - Non Pajak: total = amount
     * - Eksklusif: total = amount + (amount * rate)
     * - Inklusif: total = amount (already includes PPN), DPP = amount * 100/(100+rate)
     */
    public static function compute(float $amount, float $ratePercent, ?string $type): array
    {
        // Input validation guards
        if ($amount < 0) {
            throw new \InvalidArgumentException("TaxService::compute — amount tidak boleh negatif ({$amount})");
        }
        if ($ratePercent < 0 || $ratePercent > 100) {
            throw new \InvalidArgumentException("TaxService::compute — rate di luar rentang valid ({$ratePercent})");
        }

        $type = static::normalizeType($type);

        if ($ratePercent <= 0 || $type === 'Non Pajak') {
            $result = ['dpp' => $amount, 'ppn' => 0.0, 'total' => $amount];
        } elseif ($type === 'Eksklusif') {
            $ppn    = round($amount * ($ratePercent / 100.0), 0);
            $result = ['dpp' => $amount, 'ppn' => $ppn, 'total' => round($amount + $ppn, 0)];
        } else {
            // Inklusif
            $result = static::computeFromInclusiveGross($amount, $ratePercent);
        }

        if (function_exists('app') && app()->bound('log')) {
            Log::debug('TaxService::compute', [
                'amount'          => $amount,
                'rate'            => $ratePercent,
                'type_normalized' => $type,
                'dpp'             => $result['dpp'],
                'ppn'             => $result['ppn'],
                'total'           => $result['total'],
            ]);
        }

        return $result;
    }
}
