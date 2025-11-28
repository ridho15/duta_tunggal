<?php

namespace App\Services;

class TaxService
{
    /**
     * Normalize tax type value across typos and variants.
     */
    public static function normalizeType(?string $type): string
    {
        $t = strtolower(trim((string) $type));
        if ($t === '' || $t === 'null') {
            return 'Eksklusif'; // default system mode per business logic (amount + ppn)
        }
        return match ($t) {
            'inklusif' => 'Inklusif',
            'eksklusif', 'eklusif' => 'Eksklusif',
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
        // Apply rule per client's image:
        // PPN (inclusive) = gross * (rate - 1)% ; DPP = gross - PPN
        $ppn = $gross * max($ratePercent - 1.0, 0.0) / 100.0;
        $dpp = $gross - $ppn;
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
        $type = static::normalizeType($type);

        if ($ratePercent <= 0 || $type === 'Non Pajak') {
            return ['dpp' => $amount, 'ppn' => 0.0, 'total' => $amount];
        }

        if ($type === 'Eksklusif') {
            $ppn = $amount * ($ratePercent / 100.0);
            return ['dpp' => $amount, 'ppn' => $ppn, 'total' => $amount + $ppn];
        }

        // Inklusif
        return static::computeFromInclusiveGross($amount, $ratePercent);
    }
}
