<?php

namespace App\Http\Controllers\Manufaktur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufaktur\StoreProductionRequest;
use App\Http\Requests\Manufaktur\UpdateProductionRequest;
use App\Models\ManufacturingOrder;
use App\Models\Production;
use App\Services\ProductionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    public function index(Request $request): View
    {
        $query = Production::query()
            ->with([
                'manufacturingOrder.productionPlan.product',
                'manufacturingOrder.cabang',
                'qualityControl',
            ]);

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('production_number', 'like', "%{$search}%")
                    ->orWhereHas('manufacturingOrder', function ($mo) use ($search) {
                        $mo->where('mo_number', 'like', "%{$search}%")
                            ->orWhereHas('productionPlan.product', function ($p) use ($search) {
                                $p->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('production_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_until')) {
            $query->whereDate('production_date', '<=', $request->input('date_until'));
        }

        // KPIs calculation
        $kpiQuery = Production::query();
        $totalOutput = (clone $kpiQuery)->count();
        $totalFinished = (clone $kpiQuery)->where('status', 'finished')->count();
        $totalDraft = (clone $kpiQuery)->where('status', 'draft')->count();
        $totalQtyProduced = (clone $kpiQuery)->where('status', 'finished')->sum('quantity_produced');

        $kpis = [
            'total_output' => $totalOutput,
            'total_finished' => $totalFinished,
            'total_draft' => $totalDraft,
            'total_qty_produced' => $totalQtyProduced,
        ];

        $productions = $query->orderByDesc('production_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('manufaktur.production.index', compact('productions', 'kpis'));
    }

    public function create(Request $request): View
    {
        $spkQuery = ManufacturingOrder::query()
            ->whereIn('status', ['in_progress', 'draft'])
            ->with(['productionPlan.product', 'productionPlan.warehouse', 'productionPlan.billOfMaterial'])
            ->orderByDesc('id');

        $availableSpk = $spkQuery->get();
        $defaultNumber = $this->productionService->generateProductionNumber();
        $selectedSpkId = $request->input('spk_id');

        return view('manufaktur.production.create', compact('availableSpk', 'defaultNumber', 'selectedSpkId'));
    }

    public function store(StoreProductionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $production = Production::create([
            'production_number' => $validated['production_number'],
            'manufacturing_order_id' => $validated['manufacturing_order_id'],
            'quantity_produced' => $validated['quantity_produced'],
            'production_date' => $validated['production_date'],
            'status' => $validated['status'] ?? 'draft',
        ]);

        return redirect()
            ->route('manufaktur.production.show', $production)
            ->with('success', "Bukti Hasil Produksi {$production->production_number} berhasil dicatat.");
    }

    public function show(Production $production): View
    {
        $production->loadMissing([
            'manufacturingOrder.productionPlan.product',
            'manufacturingOrder.productionPlan.billOfMaterial.items.product',
            'manufacturingOrder.productionPlan.warehouse',
            'manufacturingOrder.cabang',
            'qualityControl.inspectedBy',
            'qualityControl.warehouse',
            'qualityControl.rak',
            'journalEntries.coa',
        ]);

        return view('manufaktur.production.show', compact('production'));
    }

    public function edit(Production $production): View|RedirectResponse
    {
        if ($production->status !== 'draft') {
            return redirect()
                ->route('manufaktur.production.show', $production)
                ->with('error', 'Hanya dokumen produksi berstatus draft yang dapat diubah.');
        }

        $production->loadMissing(['manufacturingOrder.productionPlan.product']);

        return view('manufaktur.production.edit', compact('production'));
    }

    public function update(UpdateProductionRequest $request, Production $production): RedirectResponse
    {
        if ($production->status !== 'draft') {
            return redirect()
                ->route('manufaktur.production.show', $production)
                ->with('error', 'Hanya dokumen produksi berstatus draft yang dapat diubah.');
        }

        $validated = $request->validated();
        $production->update($validated);

        return redirect()
            ->route('manufaktur.production.show', $production)
            ->with('success', "Data hasil produksi {$production->production_number} berhasil diperbarui.");
    }

    public function destroy(Production $production): RedirectResponse
    {
        if ($production->status !== 'draft') {
            return redirect()
                ->route('manufaktur.production.show', $production)
                ->with('error', 'Dokumen produksi yang sudah selesai (finished) tidak dapat dihapus.');
        }

        $number = $production->production_number;
        $production->delete();

        return redirect()
            ->route('manufaktur.production.index')
            ->with('success', "Bukti Hasil Produksi {$number} berhasil dihapus.");
    }

    public function finish(Production $production): RedirectResponse
    {
        if ($production->status !== 'draft') {
            return redirect()
                ->route('manufaktur.production.show', $production)
                ->with('error', 'Dokumen produksi ini sudah diselesaikan sebelumnya.');
        }

        $plannedQuantity = $production->manufacturingOrder?->productionPlan?->quantity;

        $production->update([
            'status' => 'finished',
            'quantity_produced' => $production->quantity_produced ?? $plannedQuantity,
        ]);

        return redirect()
            ->route('manufaktur.production.show', $production)
            ->with('success', "Produksi {$production->production_number} telah diselesaikan. Quality Control otomatis diterbitkan untuk inspeksi mutu barang jadi.");
    }

    public function pdf(Production $production): Response
    {
        $production->loadMissing([
            'manufacturingOrder.productionPlan.product',
            'manufacturingOrder.productionPlan.billOfMaterial',
            'manufacturingOrder.productionPlan.warehouse',
            'manufacturingOrder.cabang',
            'qualityControl.inspectedBy',
        ]);

        $pdf = Pdf::loadView('manufaktur.production.pdf', compact('production'))
            ->setPaper('a4', 'portrait');

        $filename = "LHP_{$production->production_number}.pdf";

        return $pdf->stream($filename);
    }

    public function apiSpkDetails(Request $request): JsonResponse
    {
        $spkId = $request->input('spk_id');
        $spk = ManufacturingOrder::with(['productionPlan.product', 'productionPlan.warehouse', 'productionPlan.billOfMaterial'])
            ->find($spkId);

        if (!$spk) {
            return response()->json(['error' => 'SPK tidak ditemukan'], 404);
        }

        $plan = $spk->productionPlan;
        $product = $plan?->product;

        return response()->json([
            'id' => $spk->id,
            'mo_number' => $spk->mo_number,
            'status' => $spk->status,
            'product_id' => $product?->id,
            'product_name' => $product?->name,
            'product_sku' => $product?->sku,
            'product_uom' => $product?->uom?->name ?? 'Pcs',
            'planned_quantity' => (float) ($plan?->quantity ?? 0),
            'warehouse_id' => $plan?->warehouse_id,
            'warehouse_name' => $plan?->warehouse?->name,
        ]);
    }
}
