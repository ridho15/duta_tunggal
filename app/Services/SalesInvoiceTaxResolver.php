<?php

namespace App\Services;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SaleOrder;

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
            $resolvedTaxType = 'None';
        }

        return [
            'tax' => $resolvedTaxRate,
            'ppn_rate' => $resolvedTaxRate,
            'tipe_pajak' => $resolvedTaxType,
        ];
    }
}