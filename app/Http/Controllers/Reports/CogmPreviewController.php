<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CogmPreviewController extends Controller
{
    public function preview(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date',   now()->endOfMonth()->toDateString());
        $cabangId  = $request->input('cabang_id');
        $productId = $request->input('product_id');

        try {
            $report = $this->computeCogm($startDate, $endDate, $cabangId, $productId);
        } catch (\Throwable $e) {
            Log::error('[CogmPreview] failed: ' . $e->getMessage());
            $report = $this->emptyReport($startDate, $endDate);
        }

        $selectedCabang  = $cabangId  ? Cabang::find($cabangId)  : null;
        $selectedProduct = $productId ? Product::find($productId) : null;

        return response()->view('reports.preview.cogm', [
            'report'          => $report,
            'startDate'       => Carbon::parse($startDate),
            'endDate'         => Carbon::parse($endDate),
            'selectedCabang'  => $selectedCabang,
            'selectedProduct' => $selectedProduct,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function computeCogm(string $startDate, string $endDate, ?int $cabangId, ?int $productId): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        // Manufacturing orders in period
        $moQuery = ManufacturingOrder::whereBetween('created_at', [$start, $end]);
        if ($cabangId) {
            $moQuery->where('cabang_id', $cabangId);
        }
        if ($productId) {
            $moQuery->whereHas('productionPlan', fn ($q) => $q->where('product_id', $productId));
        }
        $orders = $moQuery->with(['productionPlan.product', 'productionPlan.billOfMaterial'])->get();

        // COA lookups
        $rawMaterialCoa = ChartOfAccount::where('name', 'like', '%Bahan Baku%')
            ->orWhere('name', 'like', '%Raw Material%')
            ->orWhere('name', 'like', '%Material Issue%')
            ->pluck('id');

        $laborCoa = ChartOfAccount::where('name', 'like', '%Tenaga Kerja%')
            ->orWhere('name', 'like', '%Direct Labor%')
            ->orWhere('name', 'like', '%Upah%')
            ->pluck('id');

        $overheadCoa = ChartOfAccount::where('name', 'like', '%Overhead%')
            ->orWhere('name', 'like', '%BOP%')
            ->pluck('id');

        $wipCoa = ChartOfAccount::where('name', 'like', '%WIP%')
            ->orWhere('name', 'like', '%Barang Dalam Proses%')
            ->pluck('id');

        $rawMaterialUsed = $this->sumJe($rawMaterialCoa, $start, $end, 'debit', $cabangId);
        $laborCost       = $this->sumJe($laborCoa,       $start, $end, 'debit', $cabangId);
        $overhead        = $this->sumJe($overheadCoa,    $start, $end, 'debit', $cabangId);

        $epoch = Carbon::createFromTimestamp(0);
        $openingWip = $this->sumJe($wipCoa, $epoch, $start->copy()->subDay(), 'debit', $cabangId)
                    - $this->sumJe($wipCoa, $epoch, $start->copy()->subDay(), 'credit', $cabangId);
        $closingWip = $this->sumJe($wipCoa, $epoch, $end, 'debit', $cabangId)
                    - $this->sumJe($wipCoa, $epoch, $end, 'credit', $cabangId);

        $totalCostAdded = $rawMaterialUsed + $laborCost + $overhead;
        $cogm           = $openingWip + $totalCostAdded - $closingWip;

        return [
            'orders'            => $orders,
            'opening_wip'       => $openingWip,
            'raw_material_used' => $rawMaterialUsed,
            'labor_cost'        => $laborCost,
            'overhead'          => $overhead,
            'total_cost_added'  => $totalCostAdded,
            'total_wip'         => $openingWip + $totalCostAdded,
            'closing_wip'       => $closingWip,
            'cogm'              => $cogm,
            'mo_count'          => $orders->count(),
        ];
    }

    private function sumJe($ids, Carbon $start, Carbon $end, string $col, ?int $cabangId): float
    {
        if (empty($ids) || (is_object($ids) && $ids->isEmpty())) {
            return 0.0;
        }

        return (float) JournalEntry::whereIn('coa_id', $ids)
            ->whereBetween('date', [$start, $end])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->sum($col);
    }

    private function emptyReport(string $start, string $end): array
    {
        return [
            'orders'            => collect(),
            'opening_wip'       => 0,
            'raw_material_used' => 0,
            'labor_cost'        => 0,
            'overhead'          => 0,
            'total_cost_added'  => 0,
            'total_wip'         => 0,
            'closing_wip'       => 0,
            'cogm'              => 0,
            'mo_count'          => 0,
        ];
    }
}
