<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairPurchaseOrderTotal extends Command
{
    protected $signature = 'procurement:repair-po-total
        {--po=1 : Purchase order ID to repair. Defaults to PO 1.}
        {--apply : Apply the update. Without this option the command only reports the proposed change.}
        {--restore= : Restore total_amount to this exact value instead of calculating from items.}';

    protected $description = 'Reversibly repair a purchase order header total_amount from its stored items and PO currency rates.';

    public function handle(PurchaseOrderService $purchaseOrderService): int
    {
        $poId = $this->positiveIntegerOption('po');
        if ($poId === null) {
            return self::FAILURE;
        }

        $restoreValue = $this->option('restore');
        if ($restoreValue !== null && ! is_numeric($restoreValue)) {
            $this->error('Option --restore must be a numeric total_amount value.');

            return self::FAILURE;
        }

        $purchaseOrder = PurchaseOrder::withoutGlobalScopes()
            ->with(['purchaseOrderItem.currency', 'purchaseOrderCurrency.currency'])
            ->find($poId);

        if (! $purchaseOrder) {
            $this->error("Purchase order {$poId} was not found.");

            return self::FAILURE;
        }

        $oldTotal = number_format((float) ($purchaseOrder->total_amount ?? 0), 2, '.', '');
        $computedTotal = number_format($purchaseOrderService->calculateTotalAmount($purchaseOrder), 2, '.', '');
        $nextTotal = $restoreValue !== null
            ? number_format((float) $restoreValue, 2, '.', '')
            : $computedTotal;

        $this->table(
            ['PO ID', 'PO Number', 'Old Total', 'Computed Total', 'Next Total', 'Mode'],
            [[
                $purchaseOrder->id,
                $purchaseOrder->po_number,
                $oldTotal,
                $computedTotal,
                $nextTotal,
                $restoreValue !== null ? 'restore' : 'repair',
            ]]
        );

        $this->line('Rollback command for the current value:');
        $this->line("php artisan procurement:repair-po-total --po={$purchaseOrder->id} --restore={$oldTotal} --apply");

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to update this purchase order.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($purchaseOrder, $nextTotal): void {
            PurchaseOrder::withoutGlobalScopes()
                ->whereKey($purchaseOrder->id)
                ->update(['total_amount' => $nextTotal]);
        });

        $this->info("Updated purchase order {$purchaseOrder->id} total_amount from {$oldTotal} to {$nextTotal}.");

        return self::SUCCESS;
    }

    protected function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if (! is_numeric($value) || (int) $value <= 0) {
            $this->error("Option --{$name} must be a positive numeric ID.");

            return null;
        }

        return (int) $value;
    }
}
