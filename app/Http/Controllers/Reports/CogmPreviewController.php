<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Services\Reports\HppReportService;
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

        $filters = [];

        if ($cabangId) {
            $filters['branches'] = [$cabangId];
        }

        // Manufacturing orders in period
        $moQuery = ManufacturingOrder::whereBetween('created_at', [$start, $end]);
        if ($cabangId) {
            $moQuery->where('cabang_id', $cabangId);
        }
        if ($productId) {
            $moQuery->whereHas('productionPlan', fn ($q) => $q->where('product_id', $productId));
        }
        $orders = $moQuery->with(['productionPlan.product', 'productionPlan.billOfMaterial'])->get();

        $report = app(HppReportService::class)->generate(
            $start->toDateString(),
            $end->toDateString(),
            $filters,
        );

        $openingWip = (float) ($report['wip']['opening'] ?? 0);
        $closingWip = (float) ($report['wip']['closing'] ?? 0);
        $rawMaterialUsed = (float) ($report['raw_materials']['used'] ?? 0);
        $laborCost = (float) ($report['direct_labor'] ?? 0);
        $overhead = (float) ($report['overhead']['total'] ?? 0);
        $totalCostAdded = (float) ($report['production_cost'] ?? 0);
        $cogm = (float) ($report['cogm'] ?? 0);

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
