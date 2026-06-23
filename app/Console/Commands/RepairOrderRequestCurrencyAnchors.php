<?php

namespace App\Console\Commands;

use App\Filament\Resources\OrderRequestResource;
use App\Helpers\MoneyHelper;
use App\Models\OrderRequestItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairOrderRequestCurrencyAnchors extends Command
{
    protected $signature = 'procurement:repair-order-request-currency-anchors
        {--apply : Apply updates. Without this option the command only reports proposed changes.}
        {--order-request= : Limit repair to one order request ID.}
        {--item= : Limit repair to one order request item ID.}
        {--unit-idr= : Manual unit price IDR anchor.}
        {--original-idr= : Manual original price IDR anchor.}
        {--repair-supplier-price : Also update product_supplier.supplier_price for the matching product/supplier.}';

    protected $description = 'Repair Order Request item currency anchors and precise foreign prices after display-rounding drift.';

    public function handle(): int
    {
        $orderRequestId = $this->positiveIntegerOption('order-request');
        $itemId = $this->positiveIntegerOption('item');
        $manualUnitIdr = $this->moneyOption('unit-idr');
        $manualOriginalIdr = $this->moneyOption('original-idr');

        if ($orderRequestId === false || $itemId === false || $manualUnitIdr === false || $manualOriginalIdr === false) {
            return self::FAILURE;
        }

        if ($manualOriginalIdr === null && $manualUnitIdr !== null) {
            $manualOriginalIdr = $manualUnitIdr;
        }

        $apply = (bool) $this->option('apply');
        $repairSupplierPrice = (bool) $this->option('repair-supplier-price');

        $rows = $this->candidateItems(
            is_int($orderRequestId) ? $orderRequestId : null,
            is_int($itemId) ? $itemId : null,
            $manualUnitIdr,
            $manualOriginalIdr
        );

        if ($rows->isEmpty()) {
            $this->info('No Order Request currency anchor repairs found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Item ID', 'OR ID', 'Currency', 'Unit IDR', 'Next Unit IDR', 'Unit Price', 'Next Unit Price', 'Subtotal', 'Next Subtotal'],
            $rows->map(fn(array $row): array => [
                $row['item_id'],
                $row['order_request_id'],
                $row['currency'],
                $row['current_unit_idr'],
                $row['next_unit_idr'],
                $row['current_unit_price'],
                $row['next_unit_price'],
                $row['current_subtotal'],
                $row['next_subtotal'],
            ])->all()
        );

        if (! $apply) {
            $this->warn('Dry run only. Re-run with --apply to update these Order Request items.');
            if ($repairSupplierPrice) {
                $this->warn('--repair-supplier-price was supplied, but supplier prices are not updated in dry-run mode.');
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows, $repairSupplierPrice): void {
            foreach ($rows as $row) {
                OrderRequestItem::withoutGlobalScopes()
                    ->whereKey($row['item_id'])
                    ->update([
                        'unit_price_idr' => $row['next_unit_idr'],
                        'original_price_idr' => $row['next_original_idr'],
                        'unit_price' => $row['next_unit_price'],
                        'original_price' => $row['next_original_price'],
                        'subtotal' => $row['next_subtotal'],
                    ]);

                if ($repairSupplierPrice && $row['product_id'] && $row['supplier_id']) {
                    DB::table('product_supplier')
                        ->where('product_id', $row['product_id'])
                        ->where('supplier_id', $row['supplier_id'])
                        ->update(['supplier_price' => $row['next_unit_idr']]);
                }
            }
        });

        $this->info('Updated ' . $rows->count() . ' Order Request item(s).');

        if ($repairSupplierPrice) {
            $this->info('Updated matching product_supplier.supplier_price rows where product/supplier existed.');
        }

        return self::SUCCESS;
    }

    protected function candidateItems(?int $orderRequestId, ?int $itemId, ?string $manualUnitIdr, ?string $manualOriginalIdr)
    {
        return OrderRequestItem::withoutGlobalScopes()
            ->with(['currency', 'orderRequest'])
            ->when($orderRequestId, fn($query) => $query->where('order_request_id', $orderRequestId))
            ->when($itemId, fn($query) => $query->whereKey($itemId))
            ->get()
            ->map(function (OrderRequestItem $item) use ($manualUnitIdr, $manualOriginalIdr): ?array {
                $currencyId = is_numeric($item->currency_id) ? (int) $item->currency_id : null;
                $unitIdr = $manualUnitIdr ?? $this->existingAnchor($item->unit_price_idr);
                $originalIdr = $manualOriginalIdr ?? $this->existingAnchor($item->original_price_idr);

                if ($unitIdr === null) {
                    return null;
                }

                $originalIdr ??= $unitIdr;

                $nextUnitPrice = OrderRequestResource::convertIdrAnchorToCurrency($unitIdr, $currencyId);
                $nextOriginalPrice = OrderRequestResource::convertIdrAnchorToCurrency($originalIdr, $currencyId);
                $nextSubtotal = $this->calculateSubtotal($item, (float) $nextUnitPrice);

                $changed = $this->isDifferent($item->unit_price_idr, $unitIdr, 2)
                    || $this->isDifferent($item->original_price_idr, $originalIdr, 2)
                    || $this->isDifferent($item->unit_price, $nextUnitPrice, 6)
                    || $this->isDifferent($item->original_price, $nextOriginalPrice, 6)
                    || $this->isDifferent($item->subtotal, $nextSubtotal, 2);

                if (! $changed) {
                    return null;
                }

                return [
                    'item_id' => $item->id,
                    'order_request_id' => $item->order_request_id,
                    'product_id' => $item->product_id,
                    'supplier_id' => $item->supplier_id,
                    'currency' => $item->currency?->code ?? (string) $item->currency_id,
                    'current_unit_idr' => number_format((float) ($item->unit_price_idr ?? 0), 2, '.', ''),
                    'next_unit_idr' => $unitIdr,
                    'next_original_idr' => $originalIdr,
                    'current_unit_price' => (string) $item->unit_price,
                    'next_unit_price' => $nextUnitPrice,
                    'next_original_price' => $nextOriginalPrice,
                    'current_subtotal' => number_format((float) ($item->subtotal ?? 0), 2, '.', ''),
                    'next_subtotal' => $nextSubtotal,
                ];
            })
            ->filter()
            ->values();
    }

    protected function calculateSubtotal(OrderRequestItem $item, float $unitPrice): string
    {
        $preview = OrderRequestResource::calculateApprovalItemPreview(
            (float) ($item->quantity ?? 0),
            $unitPrice,
            (float) ($item->discount ?? 0),
            (float) ($item->tax ?? 0),
            OrderRequestResource::taxServiceTypeFromItemTaxType($item->tipe_pajak ?? null)
        );

        return number_format((float) $preview['subtotal'], 2, '.', '');
    }

    protected function existingAnchor(mixed $value): ?string
    {
        $parsed = MoneyHelper::parseHighPrecision($value ?? 0);

        return (float) $parsed > 0 ? number_format((float) $parsed, 2, '.', '') : null;
    }

    protected function isDifferent(mixed $current, mixed $next, int $precision): bool
    {
        return abs(round((float) $current, $precision) - round((float) $next, $precision)) > (10 ** -$precision);
    }

    protected function positiveIntegerOption(string $name): int|bool|null
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            $this->error("Option --{$name} must be a positive integer.");

            return false;
        }

        return (int) $value;
    }

    protected function moneyOption(string $name): string|bool|null
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        $parsed = MoneyHelper::parseHighPrecision($value);
        if ((float) $parsed <= 0) {
            $this->error("Option --{$name} must be a positive money amount.");

            return false;
        }

        return number_format((float) $parsed, 2, '.', '');
    }
}
