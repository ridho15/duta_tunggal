<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InventoryStock;
use App\Models\StockMovement;

class AuditInventoryConsistency extends Command
{
    protected $signature = 'audit:inventory-consistency {--product_id=} {--warehouse_id=} {--rak_id=}';
    protected $description = 'Audit InventoryStock.qty_available against summed StockMovement history (manufacture/purchase/transfer in-out)';

    public function handle(): int
    {
        $query = InventoryStock::query();
        if ($pid = $this->option('product_id')) $query->where('product_id', $pid);
        if ($wid = $this->option('warehouse_id')) $query->where('warehouse_id', $wid);
        if ($rid = $this->option('rak_id')) $query->where('rak_id', $rid);

        $stocks = $query->with(['product', 'warehouse', 'rak'])->get();
        if ($stocks->isEmpty()) {
            $this->info('No inventory stocks found for given filters.');
            return self::SUCCESS;
        }

        $headers = ['Product', 'Warehouse', 'Rak', 'Qty Available', 'Calc From Movements', 'Delta'];
        $rows = [];
        $issues = 0;

        foreach ($stocks as $s) {
            $inTypes = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
            $outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

            $in = StockMovement::where('product_id', $s->product_id)
                ->where('warehouse_id', $s->warehouse_id)
                ->when($s->rak_id, fn($q) => $q->where('rak_id', $s->rak_id))
                ->whereIn('type', $inTypes)
                ->sum('quantity');

            $out = StockMovement::where('product_id', $s->product_id)
                ->where('warehouse_id', $s->warehouse_id)
                ->when($s->rak_id, fn($q) => $q->where('rak_id', $s->rak_id))
                ->whereIn('type', $outTypes)
                ->sum('quantity');

            $calc = $in - $out;
            $delta = (float)$s->qty_available - (float)$calc;
            $isOk = abs($delta) < 1e-6;
            if (!$isOk) $issues++;

            $rows[] = [
                optional($s->product)->name . ' (#' . $s->product_id . ')',
                optional($s->warehouse)->name . ' (#' . $s->warehouse_id . ')',
                optional($s->rak)->name . ' (#' . ($s->rak_id ?? '-') . ')',
                $s->qty_available,
                $calc,
                $delta,
            ];
        }

        $this->table($headers, $rows);
        $this->line('Total records: ' . count($rows) . ', Issues: ' . $issues);

        return self::SUCCESS;
    }
}
