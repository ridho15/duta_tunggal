<?php

namespace App\Support;

class TaxTypeHelper
{
    public const NONE = 'none';
    public const INKLUSIF = 'inklusif';
    public const EKLUSIF = 'eklusif';

    public static function normalize(?string $value, string $default = self::EKLUSIF): string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'none', 'non pajak', 'non-pajak', 'nonpajak' => self::NONE,
            'inklusif', 'inclusive', 'ppn included', 'included', 'ppn-included' => self::INKLUSIF,
            'eklusif', 'eksklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => self::EKLUSIF,
            default => $default,
        };
    }

    public static function options(): array
    {
        return [
            self::NONE => 'Non Pajak',
            self::INKLUSIF => 'Inklusif',
            self::EKLUSIF => 'Eklusif',
        ];
    }

    public static function serviceType(?string $value): string
    {
        return match (self::normalize($value)) {
            self::NONE => 'None',
            self::INKLUSIF => 'PPN Included',
            default => 'PPN Excluded',
        };
    }
}
