<?php

namespace App\Services;

use App\Support\LineAmounts;
use Illuminate\Support\Facades\Schema;

/**
 * Membentuk atribut baris invoice PENJUALAN yang seragam untuk SEMUA jalur pembuatan
 * (invoice otomatis dari Sales Order, dari Delivery Order, dan form Filament) — Fase 5B.
 *
 *   price           = harga satuan GROSS (bukan lagi net pada sebagian jalur)
 *   discount        = persen diskon
 *   gross_amount    = qty × price
 *   discount_amount = nominal diskon (Rp)
 *   subtotal        = DPP
 *   tax_rate / tax_amount = tarif dan nominal PPN
 *   total           = DPP + PPN (Inklusif: nilai setelah diskon)
 *
 * Perhitungan hanya lewat LineAmounts::calculate() sehingga total baris pasti sama dengan total di Sales Order.
 */
class SalesInvoiceLineBuilder
{
    private static ?bool $hasBreakdownColumns = null;

    /**
     * @param  callable|null  $convert  fn (float $amount): float — konversi ke IDR bila perlu (mata uang asing)
     * @return array<string, mixed>
     */
    public function attributes(
        int|string|null $productId,
        float $quantity,
        float $unitPriceGross,
        float $discountPct,
        float $taxRate,
        ?string $taxType,
        int|string|null $coaId = null,
        ?callable $convert = null,
    ): array {
        $amounts = LineAmounts::calculate($quantity, $unitPriceGross, $discountPct, $taxRate, $taxType);
        $convert ??= fn (float $amount): float => $amount;

        $attributes = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $convert($unitPriceGross),
            'discount' => $amounts['discount_pct'],
            'tax_rate' => $amounts['tax_rate'],
            'tax_amount' => $convert($amounts['ppn']),
            'subtotal' => $convert($amounts['dpp']),
            'total' => $convert($amounts['total']),
            'coa_id' => $coaId,
        ];

        // Aman sebelum migrasi kolom rincian dijalankan.
        if (self::hasBreakdownColumns()) {
            $attributes['gross_amount'] = $convert($amounts['gross']);
            $attributes['discount_amount'] = $convert($amounts['discount_amount']);
        }

        return $attributes;
    }

    /**
     * Baris invoice dari FORM Filament (harga di form berupa harga NET setelah diskon dan terkunci mengikuti SO/DO).
     * Setiap baris dicocokkan dengan item Sales Order-nya (produk yang sama) untuk mendapat harga GROSS dan diskon %;
     * tarif dan tipe pajak mengikuti header invoice. Hasilnya disimpan dengan rincian baku yang sama seperti jalur otomatis.
     * Baris tanpa pasangan item SO memakai harga form sebagai gross tanpa diskon.
     *
     * @param  array<int, array<string, mixed>>  $formItems
     * @return array{items: array<int, array<string, mixed>>, matched: bool}  matched=false bila ada baris tanpa pasangan SO
     */
    public function fromFormItems(\App\Models\Invoice $invoice, array $formItems): array
    {
        $saleOrder = $invoice->from_model_type === \App\Models\SaleOrder::class
            ? \App\Models\SaleOrder::withoutGlobalScopes()->with('saleOrderItem')->find($invoice->from_model_id)
            : null;
        $soItems = $saleOrder?->saleOrderItem ?? collect();
        $used = [];
        $matched = true;
        $rows = [];

        // Tipe & tarif pajak mengikuti HEADER invoice (form mengizinkan user mengubahnya dari nilai SO);
        // item SO hanya menyumbang harga gross dan diskon %.
        $rate = (float) ($invoice->ppn_rate ?? $invoice->tax ?? 0);
        $type = ($invoice->tipe_pajak ?? 'None') === 'None' ? 'Non Pajak' : $invoice->tipe_pajak;

        foreach ($formItems as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = (float) \App\Helpers\MoneyHelper::safeParse($item['quantity'] ?? 0);
            $coaId = $item['coa_id'] ?? null;

            $soItem = $soItems->first(fn ($candidate) => $candidate->product_id == $productId && ! in_array($candidate->id, $used, true));

            if ($soItem) {
                $used[] = $soItem->id;
                $rows[] = $this->attributes($productId, $quantity, (float) $soItem->unit_price, (float) $soItem->discount, $rate, $type, $coaId);
            } else {
                $matched = false;
                $formPrice = (float) \App\Helpers\MoneyHelper::safeParse($item['price'] ?? 0);
                $rows[] = $this->attributes($productId, $quantity, $formPrice, 0.0, $rate, $type, $coaId);
            }
        }

        return ['items' => $rows, 'matched' => $matched];
    }

    /** Total biaya lain invoice (other_fee) tanpa pemotongan desimal. */
    public function otherFeeTotal(\App\Models\Invoice $invoice): float
    {
        $fees = $invoice->other_fee;
        if (! $fees) {
            return 0.0;
        }

        return round(collect((array) $fees)->sum(fn ($fee) => is_array($fee)
            ? (float) \App\Helpers\MoneyHelper::safeParse($fee['amount'] ?? 0)
            : (float) \App\Helpers\MoneyHelper::safeParse($fee)), 2);
    }

    public static function hasBreakdownColumns(): bool
    {
        return self::$hasBreakdownColumns ??= Schema::hasColumn('invoice_items', 'gross_amount')
            && Schema::hasColumn('invoice_items', 'discount_amount');
    }

    /** Untuk pengujian: paksa deteksi ulang kolom. */
    public static function flushColumnCache(): void
    {
        self::$hasBreakdownColumns = null;
    }
}
