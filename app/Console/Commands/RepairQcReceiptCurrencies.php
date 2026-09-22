<?php

namespace App\Console\Commands;

use App\Models\PurchaseReceipt;
use App\Models\QualityControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairQcReceiptCurrencies extends Command
{
    protected $signature = 'procurement:repair-qc-receipt-currencies
        {--apply : Apply updates. Without this option the command only reports mismatches.}
        {--qc= : Limit repair to one quality control ID.}';

    protected $description = 'Repair auto-created QC purchase receipt currency headers so they match the related purchase order item currency.';

    public function handle(): int
    {
        $qcId = $this->option('qc');
        if ($qcId !== null && (! is_numeric($qcId) || (int) $qcId <= 0)) {
            $this->error('Option --qc must be a positive numeric quality control ID.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $rows = $this->candidateReceipts($qcId ? (int) $qcId : null);

        if ($rows->isEmpty()) {
            $this->info('No QC auto-created purchase receipt currency mismatches found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Receipt ID', 'Receipt No', 'QC ID', 'Current Currency', 'Expected Currency', 'PO Item ID'],
            $rows->map(fn(array $row): array => [
                $row['receipt_id'],
                $row['receipt_number'],
                $row['quality_control_id'] ?? '-',
                $row['current_currency'],
                $row['expected_currency'],
                $row['purchase_order_item_id'],
            ])->all()
        );

        if (! $apply) {
            $this->warn('Dry run only. Re-run with --apply to update these receipt headers.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                PurchaseReceipt::withoutGlobalScopes()
                    ->whereKey($row['receipt_id'])
                    ->update(['currency_id' => $row['expected_currency_id']]);
            }
        });

        $this->info('Updated ' . $rows->count() . ' receipt currency header(s).');

        return self::SUCCESS;
    }

    protected function candidateReceipts(?int $qualityControlId = null)
    {
        return PurchaseReceipt::withoutGlobalScopes()
            ->with([
                'currency',
                'purchaseReceiptItem.purchaseOrderItem.currency',
            ])
            ->withCount('purchaseReceiptItem')
            ->where('notes', 'like', 'Auto-created from QC:%')
            ->when($qualityControlId, function ($query) use ($qualityControlId): void {
                $qc = QualityControl::withoutGlobalScopes()->find($qualityControlId);

                if (! $qc) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where('notes', 'like', '%' . $qc->qc_number . '%');
            })
            ->get()
            ->filter(fn(PurchaseReceipt $receipt): bool => (int) $receipt->purchase_receipt_item_count === 1)
            ->map(function (PurchaseReceipt $receipt): ?array {
                $item = $receipt->purchaseReceiptItem->first();
                $poItem = $item?->purchaseOrderItem;
                $expectedCurrencyId = is_numeric($poItem?->currency_id ?? null) ? (int) $poItem->currency_id : null;

                if (! $item || ! $poItem || ! $expectedCurrencyId || (int) $receipt->currency_id === $expectedCurrencyId) {
                    return null;
                }

                $qualityControl = QualityControl::withoutGlobalScopes()
                    ->where('from_model_type', \App\Models\PurchaseOrderItem::class)
                    ->where('from_model_id', $poItem->id)
                    ->where('qc_number', $this->extractQcNumber($receipt->notes ?? ''))
                    ->first();

                return [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'quality_control_id' => $qualityControl?->id,
                    'purchase_order_item_id' => $poItem->id,
                    'current_currency' => $receipt->currency?->code ?? (string) $receipt->currency_id,
                    'expected_currency' => $poItem->currency?->code ?? (string) $expectedCurrencyId,
                    'expected_currency_id' => $expectedCurrencyId,
                ];
            })
            ->filter()
            ->values();
    }

    protected function extractQcNumber(string $notes): ?string
    {
        if (preg_match('/Auto-created from QC:\s*([^\s]+)/', $notes, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
