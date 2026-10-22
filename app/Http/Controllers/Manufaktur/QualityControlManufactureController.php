<?php

namespace App\Http\Controllers\Manufaktur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufaktur\ProcessQualityControlManufactureRequest;
use App\Http\Requests\Manufaktur\StoreQualityControlManufactureRequest;
use App\Models\Production;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QualityControlManufactureController extends Controller
{
    public function __construct(
        protected QualityControlService $qualityControlService
    ) {}

    public function index(Request $request): View
    {
        $query = QualityControl::query()
            ->where('from_model_type', Production::class)
            ->with([
                'production.manufacturingOrder.productionPlan.product',
                'fromModel',
                'product',
                'inspectedBy',
                'warehouse',
                'rak',
                'cabang',
            ]);

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('qc_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    })
                    ->orWhereHasMorph('fromModel', [Production::class], function ($prod) use ($search) {
                        $prod->where('production_number', 'like', "%{$search}%")
                            ->orWhereHas('manufacturingOrder', function ($mo) use ($search) {
                                $mo->where('mo_number', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Warehouse filter
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_until')) {
            $query->whereDate('created_at', '<=', $request->input('date_until'));
        }

        // KPIs calculation
        $kpiBase = QualityControl::where('from_model_type', Production::class);
        $totalQc = (clone $kpiBase)->count();
        $pendingQc = (clone $kpiBase)->where('status', 0)->count();
        $processedQc = (clone $kpiBase)->where('status', 1)->count();
        $totalPassed = (clone $kpiBase)->where('status', 1)->sum('passed_quantity');
        $totalRejected = (clone $kpiBase)->where('status', 1)->sum('rejected_quantity');

        $kpis = [
            'total_qc' => $totalQc,
            'pending_qc' => $pendingQc,
            'processed_qc' => $processedQc,
            'total_passed' => $totalPassed,
            'total_rejected' => $totalRejected,
        ];

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        $qcList = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('manufaktur.qc.index', compact('qcList', 'kpis', 'warehouses'));
    }

    public function create(): View
    {
        $qualityControlTable = (new QualityControl())->getTable();
        $productionTable = (new Production())->getTable();

        // Cari produksi yang finished tapi belum memiliki QC
        $availableProductions = Production::query()
            ->with(['manufacturingOrder.productionPlan.product', 'manufacturingOrder.productionPlan.warehouse'])
            ->whereIn('status', ['finished', 'completed'])
            ->whereNotExists(function ($subQuery) use ($qualityControlTable, $productionTable) {
                $subQuery->selectRaw('1')
                    ->from($qualityControlTable)
                    ->whereColumn("{$qualityControlTable}.from_model_id", "{$productionTable}.id")
                    ->where("{$qualityControlTable}.from_model_type", Production::class)
                    ->whereNull("{$qualityControlTable}.deleted_at");
            })
            ->orderByDesc('production_date')
            ->get();

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();
        $inspectors = User::orderBy('name')->get();
        $defaultNumber = $this->qualityControlService->generateQcManufactureNumber();

        return view('manufaktur.qc.create', compact('availableProductions', 'warehouses', 'inspectors', 'defaultNumber'));
    }

    public function store(StoreQualityControlManufactureRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $production = Production::with(['manufacturingOrder.productionPlan.product'])->findOrFail($validated['from_model_id']);
        $product = $production->manufacturingOrder?->productionPlan?->product;

        $qc = QualityControl::create([
            'qc_number' => $validated['qc_number'],
            'from_model_type' => Production::class,
            'from_model_id' => $production->id,
            'product_id' => $product?->id,
            'warehouse_id' => $validated['warehouse_id'],
            'rak_id' => $validated['rak_id'] ?? null,
            'passed_quantity' => $validated['passed_quantity'],
            'rejected_quantity' => $validated['rejected_quantity'],
            'reason_reject' => $validated['reason_reject'] ?? null,
            'inspected_by' => $validated['inspected_by'],
            'notes' => $validated['notes'] ?? null,
            'status' => 0,
            'cabang_id' => $production->manufacturingOrder?->cabang_id ?? Auth::user()?->cabang_id,
        ]);

        return redirect()
            ->route('manufaktur.qc.show', $qc)
            ->with('success', "Dokumen Quality Control {$qc->qc_number} berhasil dibuat.");
    }

    public function show(QualityControl $qc): View
    {
        $qc->loadMissing([
            'production.manufacturingOrder.productionPlan.product',
            'production.manufacturingOrder.cabang',
            'fromModel',
            'product.uom',
            'inspectedBy',
            'warehouse',
            'rak',
            'journalEntries.coa',
            'stockMovement',
            'cabang',
        ]);

        if ($qc->fromModel instanceof Production) {
            $qc->fromModel->loadMissing(['manufacturingOrder.productionPlan.product', 'manufacturingOrder.cabang']);
        }

        return view('manufaktur.qc.show', compact('qc'));
    }

    public function processForm(QualityControl $qc): View|RedirectResponse
    {
        if ($qc->status == 1) {
            return redirect()
                ->route('manufaktur.qc.show', $qc)
                ->with('error', 'Dokumen Quality Control ini sudah selesai diproses sebelumnya.');
        }

        $qc->loadMissing([
            'production.manufacturingOrder.productionPlan.product',
            'fromModel',
            'product.uom',
            'warehouse',
            'rak',
        ]);

        if ($qc->fromModel instanceof Production) {
            $qc->fromModel->loadMissing(['manufacturingOrder.productionPlan.product', 'manufacturingOrder.cabang']);
        }

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();
        $raks = $qc->warehouse_id ? Rak::where('warehouse_id', $qc->warehouse_id)->get() : collect();
        $inspectors = User::orderBy('name')->get();

        return view('manufaktur.qc.process', compact('qc', 'warehouses', 'raks', 'inspectors'));
    }

    public function process(ProcessQualityControlManufactureRequest $request, QualityControl $qc): RedirectResponse
    {
        if ($qc->status == 1) {
            return redirect()
                ->route('manufaktur.qc.show', $qc)
                ->with('error', 'Dokumen Quality Control ini sudah selesai diproses sebelumnya.');
        }

        $validated = $request->validated();

        $this->qualityControlService->completeQualityControl($qc, $validated);

        return redirect()
            ->route('manufaktur.qc.show', $qc)
            ->with('success', "Inspeksi QC {$qc->qc_number} berhasil diproses. Stok barang jadi telah ditambahkan dan jurnal penutupan BDP (Finished Goods) telah tercatat otomatis.");
    }

    public function pdf(QualityControl $qc): Response
    {
        $qc->loadMissing([
            'production.manufacturingOrder.productionPlan.product',
            'production.manufacturingOrder.cabang',
            'fromModel',
            'product.uom',
            'inspectedBy',
            'warehouse',
            'rak',
            'cabang',
        ]);

        if ($qc->fromModel instanceof Production) {
            $qc->fromModel->loadMissing(['manufacturingOrder.productionPlan.product', 'manufacturingOrder.cabang']);
        }

        $pdf = Pdf::loadView('manufaktur.qc.pdf', compact('qc'))
            ->setPaper('a4', 'portrait');

        $filename = "Sertifikat_QC_{$qc->qc_number}.pdf";

        return $pdf->stream($filename);
    }

    public function apiRaks(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        if (!$warehouseId) {
            return response()->json([]);
        }

        $raks = Rak::where('warehouse_id', $warehouseId)
            ->get()
            ->map(fn ($rak) => [
                'id' => $rak->id,
                'code' => $rak->code,
                'name' => $rak->name,
                'label' => filled($rak->code) ? "({$rak->code}) {$rak->name}" : ($rak->name ?? '-'),
            ]);

        return response()->json($raks);
    }
}
