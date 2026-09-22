<?php

namespace App\Services;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SaleOrder;
use App\Models\TaxSetting;

class SalesInvoiceTaxResolver
{
    /**
     * Resolve invoice-level tax fields from a Sale Order.
     */
    public function resolveFromSaleOrder(SaleOrder $saleOrder): array
    {
        $saleOrder->loadMissing('saleOrderItem');

        $firstTaxRate = $saleOrder->saleOrderItem
            ->pluck('tax')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->first();

        $resolvedTaxRate = (float) ($firstTaxRate ?? 0);

        $firstTaxType = $saleOrder->saleOrderItem
            ->pluck('tipe_pajak')
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->first();

        if ($firstTaxType !== null && trim((string) $firstTaxType) !== '') {
            $resolvedTaxType = SalesInvoiceResource::normalizeInvoiceTaxTypeValue((string) $firstTaxType);
        } elseif ($resolvedTaxRate > 0) {
            $resolvedTaxType = 'Eksklusif';
        } else {
            // If items indicate an exclusive tax type but rate is missing/zero,
            // fall back to active tax setting rate for PPN.
            $resolvedTaxType = 'None';
            if ($firstTaxType !== null) {
                $normalized = strtolower(trim((string) $firstTaxType));
                if (in_array($normalized, ['eksklusif', 'ekslusif', 'eklusif', 'exclusive'], true)) {
                    $resolvedTaxRate = (float) TaxSetting::activeRate('PPN');
                    $resolvedTaxType = SalesInvoiceResource::normalizeInvoiceTaxTypeValue((string) $firstTaxType);
                }
            }
        }

        return [
            'tax' => $resolvedTaxRate,
            'ppn_rate' => $resolvedTaxRate,
            'tipe_pajak' => $resolvedTaxType,
        ];
    }
}