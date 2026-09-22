<?php

namespace App\Support;

use App\Services\TaxService;

/**
 * SATU-SATUNYA perhitungan baris penjualan (Quotation, Sales Order, Invoice, PDF, laporan) — Fase 5B.
 *
 * Kebijakan pembulatan (keputusan D6): default 2 desimal, selaras dengan kolom decimal(15,2),
 * layar Filament, dan React. Dapat diubah lewat config('sales.line_rounding_decimals') / env
 * SALES_LINE_ROUNDING_DECIMALS (0 = rupiah bulat, perilaku lama TaxService) tanpa mengubah kode.
 *
 * Urutan (DPP dibulatkan lebih dulu, PPN dihitung dari DPP yang sudah bulat sehingga
 * DPP + PPN = Total tepat dan jurnal selalu seimbang):
 *
 *   gross            = qty × harga satuan (harga GROSS, sebelum diskon)
 *   sesudah diskon   = gross × (1 − diskon%)
 *   Eksklusif        : dpp = sesudah diskon;             ppn = dpp × tarif;  total = dpp + ppn
 *   Inklusif         : total = sesudah diskon;           dpp = total × 100/(100 + tarif);  ppn = total − dpp
 *   Non Pajak        : dpp = total = sesudah diskon;     ppn = 0
 *   discount_amount  = gross − dpp (Eksklusif/Non Pajak) atau gross − total (Inklusif), sehingga
 *                      Jumlah − Diskon = nilai sebelum PPN yang terlihat pada dokumen.
 *
 * Contoh UAT: 20 × 8.687, diskon 5%, PPN 11% eksklusif
 *   gross 173.740 | diskon 8.687 | DPP 165.053 | PPN 18.155,83 | total 183.208,83
 */
final class LineAmounts
{
    public static function decimals(): int
    {
        // Aman dipakai di unit test murni tanpa container Laravel (config belum terikat -> default 2).
        $configured = function_exists('app') && app()->bound('config')
            ? config('sales.line_rounding_decimals', 2)
            : 2;

        return max(0, min(4, (int) $configured));
    }

    /**
     * Format uang baku dokumen penjualan: selalu jumlah desimal kebijakan (default 2) — "Rp 173.740,00" — sehingga
     * PDF dan layar menampilkan angka identik dan sen tidak tersembunyi.
     */
    public static function money(float|int|string|null $amount, ?int $currencyId = null, ?int $decimals = null): string
    {
        $symbol = $currencyId ? CurrencyConversionResolver::resolveSymbol($currencyId) : 'Rp';

        return $symbol . ' ' . number_format((float) $amount, $decimals ?? self::decimals(), ',', '.');
    }

    /**
     * @param  string|null  $taxType  tipe pajak apa adanya; dinormalkan lewat TaxService::normalizeType()
     * @return array{gross: float, discount_pct: float, discount_amount: float, dpp: float, tax_rate: float, ppn: float, total: float, tax_type: string}
     */
    public static function calculate(
        float|int|string|null $quantity,
        float|int|string|null $unitPrice,
        float|int|string|null $discountPct,
        float|int|string|null $taxRate,
        ?string $taxType,
        ?int $decimals = null,
    ): array {
        $decimals ??= self::decimals();

        $qty = max(0.0, (float) $quantity);
        $price = max(0.0, (float) $unitPrice);
        $discount = max(0.0, min(100.0, (float) $discountPct));
        $rate = max(0.0, min(100.0, (float) $taxRate));
        // Varian penulisan tambahan yang dikenal HelperController::hitungSubtotal sebelumnya
        $rawType = strtolower(trim((string) $taxType));
        $type = in_array($rawType, ['included', 'ppn-included'], true) ? 'Inklusif' : TaxService::normalizeType($taxType);

        $grossRaw = $qty * $price;
        $afterDiscountRaw = $grossRaw * (1 - $discount / 100.0);

        $gross = round($grossRaw, $decimals);

        if ($rate <= 0.0 || $type === 'Non Pajak') {
            $dpp = round($afterDiscountRaw, $decimals);
            $ppn = 0.0;
            $total = $dpp;
            $rate = 0.0;
        } elseif ($type === 'Inklusif') {
            $total = round($afterDiscountRaw, $decimals);
            $dpp = round($total * (100.0 / (100.0 + $rate)), $decimals);
            $ppn = round($total - $dpp, $decimals);
        } else {
            // Eksklusif (juga default bagi tipe lain yang tidak dikenal)
            $dpp = round($afterDiscountRaw, $decimals);
            $ppn = round($dpp * ($rate / 100.0), $decimals);
            $total = round($dpp + $ppn, $decimals);
        }

        // Diskon = selisih jumlah kotor dengan nilai bersih yang tercantum (DPP, atau total pada Inklusif)
        $net = $type === 'Inklusif' && $rate > 0.0 ? $total : $dpp;
        $discountAmount = round($gross - $net, $decimals);

        return [
            'gross' => $gross,
            'discount_pct' => $discount,
            'discount_amount' => max(0.0, $discountAmount),
            'dpp' => $dpp,
            'tax_rate' => $rate,
            'ppn' => $ppn,
            'total' => $total,
            'tax_type' => $type,
        ];
    }
}
