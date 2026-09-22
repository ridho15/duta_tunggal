<?php

namespace App\Support;

use App\Helpers\MoneyHelper;
use App\Models\DeliveryOrder;
use App\Models\Quotation;
use App\Models\SaleOrder;
use Carbon\Carbon;

/**
 * Label pilihan dokumen yang informatif untuk dropdown ("SO-00004 · PT X · 12 Sep 2026 · Rp 183.208,83"),
 * agar pengguna tidak perlu membuka dokumen untuk membedakan nomor yang mirip.
 */
class DocumentLabels
{
    private const SEPARATOR = ' · ';

    public static function saleOrder(SaleOrder $saleOrder): string
    {
        return self::join([
            $saleOrder->so_number,
            $saleOrder->customer?->name,
            self::date($saleOrder->order_date),
            self::money($saleOrder->total_amount),
        ]);
    }

    /**
     * @param  float|null  $total  nilai DO (DeliveryOrderValuation); null → tanpa nilai
     * @param  bool  $hasUnlinkedItems  ada item DO tanpa tautan item SO (harganya tidak dapat ditentukan → tampil peringatan)
     */
    public static function deliveryOrder(DeliveryOrder $deliveryOrder, ?float $total = null, bool $hasUnlinkedItems = false): string
    {
        return self::join([
            $deliveryOrder->do_number,
            self::date($deliveryOrder->delivery_date),
            $total !== null ? MoneyHelper::rupiah($total) : null,
            $hasUnlinkedItems ? '⚠ ada item tanpa tautan SO' : null,
        ]);
    }

    public static function quotation(Quotation $quotation): string
    {
        return self::join([
            $quotation->quotation_number,
            $quotation->customer?->name,
            $quotation->valid_until ? 's.d. '.self::date($quotation->valid_until) : null,
            self::money($quotation->total_amount),
        ]);
    }

    /** @param  array<int, string|null>  $parts */
    private static function join(array $parts): string
    {
        return implode(self::SEPARATOR, array_values(array_filter($parts, fn ($part) => filled($part))));
    }

    private static function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->translatedFormat('d M Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function money(mixed $value): ?string
    {
        return blank($value) ? null : MoneyHelper::rupiah($value);
    }
}
