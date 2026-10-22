<?php

namespace App\Http\Controllers\Manufaktur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufaktur\StoreManufacturingOrderRequest;
use App\Http\Requests\Manufaktur\UpdateManufacturingOrderRequest;
use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ManufacturingOrderController extends Controller
{
    protected ManufacturingService $manufacturingService;

    public function __construct(ManufacturingService $manufacturingService)
    {
        $this->manufacturingService = $manufacturingService;
    }

    /**
     * Menampilkan daftar Surat Perintah Kerja (SPK / Manufacturing Order).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $baseQuery = ManufacturingOrder::with([
            'productionPlan.product.uom',
            'productionPlan.billOfMaterial',
            'productionPlan.warehouse',
            'cabang',
            'productions',
        ]);

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        } elseif ($cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        if ($startDate) {
            $baseQuery->whereDate('start_date', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('start_date', '<=', $endDate);
        }

        // Hitung KPIs
        $totalOrders = (clone $baseQuery)->count();
        $draftOrders = (clone $baseQuery)->where('status', 'draft')->count();
        $inProgressOrders = (clone $baseQuery)->where('status', 'in_progress')->count();
        $completedOrders = (clone $baseQuery)->where('status', 'completed')->count();

        // Filter tab status
        $query = clone $baseQuery;
        if ($tab === 'draft') {
            $query->where('status', 'draft');
        } elseif ($tab === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        } elseif ($tab === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        // Filter pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mo_number', 'like', "%{$search}%")
                    ->orWhereHas('productionPlan', function ($pq) use ($search) {
                        $pq->where('plan_number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhereHas('product', function ($prodQ) use ($search) {
                                $prodQ->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $cabangs = Cabang::orderBy('nama')->get();

        return view('manufaktur.spk.index', compact(
            'orders',
            'tab',
            'search',
            'cabangId',
            'startDate',
            'endDate',
            'cabangs',
            'totalOrders',
            'draftOrders',
            'inProgressOrders',
            'completedOrders'
        ));
    }

    /**
     * Form tambah Surat Perintah Kerja (SPK) baru.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $selectedBomId = $request->query('bom_id');
        $selectedPlanId = $request->query('plan_id');

        // Daftar Resep BOM aktif
        $boms = BillOfMaterial::where('is_active', true)
            ->with(['product.uom', 'items.product', 'uom'])
            ->orderBy('nama_bom')
            ->get();

        // Rencana Produksi yang belum selesai
        $plans = ProductionPlan::whereIn('status', ['draft', 'scheduled'])
            ->with(['product.uom', 'billOfMaterial', 'warehouse'])
            ->orderBy('created_at', 'desc')
            ->get();

        $warehouses = Warehouse::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $defaultMoNumber = $this->manufacturingService->generateMoNumber();

        return view('manufaktur.spk.create', compact(
            'boms',
            'plans',
            'warehouses',
            'cabangs',
            'defaultMoNumber',
            'selectedBomId',
            'selectedPlanId',
            'canManageAll'
        ));
    }

    /**
     * Menyimpan SPK baru ke database.
     */
    public function store(StoreManufacturingOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $cabangId = $validated['cabang_id'] ?? $user?->cabang_id;
        if (!$cabangId) {
            $cabangId = Cabang::first()?->id;
        }

        DB::beginTransaction();
        try {
            $quantity = (float) $validated['quantity'];
            $productionPlanId = $validated['production_plan_id'] ?? null;

            // Jika memilih Rencana Produksi
            if ($productionPlanId) {
                $plan = ProductionPlan::with('billOfMaterial.items.product')->findOrFail($productionPlanId);
                $bom = $plan->billOfMaterial;
            } else {
                // Buat ProductionPlan otomatis dari BOM yang dipilih
                $bom = BillOfMaterial::with('items.product')->findOrFail($validated['bill_of_material_id']);
                
                $warehouseId = $validated['warehouse_id'] ?? Warehouse::first()?->id;
                
                $plan = ProductionPlan::create([
                    'plan_number' => 'PLN-' . date('Ymd') . '-' . str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'name' => 'SPK: ' . $bom->nama_bom,
                    'source_type' => 'manual',
                    'bill_of_material_id' => $bom->id,
                    'product_id' => $bom->product_id,
                    'quantity' => $quantity,
                    'uom_id' => $bom->uom_id,
                    'warehouse_id' => $warehouseId,
                    'cabang_id' => $cabangId,
                    'start_date' => $validated['start_date'] ? Carbon::parse($validated['start_date']) : now(),
                    'end_date' => $validated['end_date'] ? Carbon::parse($validated['end_date']) : now()->addDays(3),
                    'status' => 'scheduled',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $user?->id ?? 1,
                ]);

                $productionPlanId = $plan->id;
            }

            // Hitung kebutuhan material
            $items = [];
            if (!empty($validated['items'])) {
                $items = $validated['items'];
            } elseif ($bom) {
                $bomBaseQty = (float) ($bom->quantity > 0 ? $bom->quantity : 1);
                $ratio = $quantity / $bomBaseQty;

                foreach ($bom->items as $bItem) {
                    $items[] = [
                        'product_id' => $bItem->product_id,
                        'uom_id' => $bItem->uom_id,
                        'quantity' => (float) $bItem->quantity * $ratio,
                        'notes' => $bItem->note ?? null,
                    ];
                }
            }

            $order = ManufacturingOrder::create([
                'mo_number' => $validated['mo_number'],
                'production_plan_id' => $productionPlanId,
                'status' => 'draft',
                'start_date' => $validated['start_date'] ? Carbon::parse($validated['start_date']) : now(),
                'end_date' => $validated['end_date'] ? Carbon::parse($validated['end_date']) : null,
                'items' => $items,
                'cabang_id' => $cabangId,
            ]);

            DB::commit();

            return redirect()
                ->route('manufaktur.spk.show', $order)
                ->with('success', "Surat Perintah Kerja (SPK) {$order->mo_number} berhasil diterbitkan dengan status Draft!");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal membuat SPK: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail Surat Perintah Kerja (SPK).
     */
    public function show(ManufacturingOrder $manufacturingOrder): View
    {
        $manufacturingOrder->load([
            'productionPlan.product.uom',
            'productionPlan.billOfMaterial.items.product.uom',
            'productionPlan.warehouse',
            'cabang',
            'productions',
        ]);

        $plan = $manufacturingOrder->productionPlan;
        $bom = $plan?->billOfMaterial;
        $warehouse = $plan?->warehouse ?? Warehouse::first();

        // Periksa ketersediaan stok fisik bahan baku di gudang
        $materialChecks = [];
        $allAvailable = true;

        $items = $manufacturingOrder->items ?: [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $requiredQty = (float) ($item['quantity'] ?? 0);
            $product = Product::with('uom')->find($productId);

            $availableStock = 0;
            if ($productId && $warehouse) {
                $stock = InventoryStock::where('product_id', $productId)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();
                $availableStock = $stock ? max(0, (float) $stock->qty_available - (float) $stock->qty_reserved) : 0;
            }

            $isSufficient = $availableStock >= $requiredQty;
            if (!$isSufficient) {
                $allAvailable = false;
            }

            $materialChecks[] = [
                'product' => $product,
                'required' => $requiredQty,
                'available' => $availableStock,
                'is_sufficient' => $isSufficient,
                'uom_name' => $product?->uom?->name ?? 'pcs',
                'notes' => $item['notes'] ?? null,
            ];
        }

        return view('manufaktur.spk.show', compact(
            'manufacturingOrder',
            'plan',
            'bom',
            'warehouse',
            'materialChecks',
            'allAvailable'
        ));
    }

    /**
     * Form edit SPK (hanya untuk status draft).
     */
    public function edit(ManufacturingOrder $manufacturingOrder): View|RedirectResponse
    {
        if ($manufacturingOrder->status !== 'draft') {
            return redirect()
                ->route('manufaktur.spk.show', $manufacturingOrder)
                ->with('error', 'Hanya SPK berstatus Draft yang dapat diubah.');
        }

        $manufacturingOrder->load(['productionPlan.product.uom', 'productionPlan.billOfMaterial.items.product', 'cabang']);

        $cabangs = Cabang::orderBy('nama')->get();

        return view('manufaktur.spk.edit', compact('manufacturingOrder', 'cabangs'));
    }

    /**
     * Memperbarui data SPK.
     */
    public function update(UpdateManufacturingOrderRequest $request, ManufacturingOrder $manufacturingOrder): RedirectResponse
    {
        if ($manufacturingOrder->status !== 'draft') {
            return redirect()
                ->route('manufaktur.spk.show', $manufacturingOrder)
                ->with('error', 'Hanya SPK berstatus Draft yang dapat diperbarui.');
        }

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $quantity = (float) $validated['quantity'];

            // Jika kuantitas berubah, sesuaikan juga qty kebutuhan material
            $items = $manufacturingOrder->items ?: [];
            $oldQty = (float) ($manufacturingOrder->quantity > 0 ? $manufacturingOrder->quantity : 1);
            $ratio = $quantity / $oldQty;

            $updatedItems = [];
            foreach ($items as $it) {
                $updatedItems[] = [
                    'product_id' => $it['product_id'],
                    'uom_id' => $it['uom_id'],
                    'quantity' => (float) $it['quantity'] * $ratio,
                    'notes' => $it['notes'] ?? null,
                ];
            }

            $manufacturingOrder->update([
                'mo_number' => $validated['mo_number'],
                'start_date' => $validated['start_date'] ? Carbon::parse($validated['start_date']) : $manufacturingOrder->start_date,
                'end_date' => $validated['end_date'] ? Carbon::parse($validated['end_date']) : $manufacturingOrder->end_date,
                'items' => !empty($validated['items']) ? $validated['items'] : $updatedItems,
                'cabang_id' => $validated['cabang_id'] ?? $manufacturingOrder->cabang_id,
            ]);

            // Sinkronkan juga quantity di ProductionPlan
            if ($manufacturingOrder->productionPlan) {
                $manufacturingOrder->productionPlan->update(['quantity' => $quantity]);
            }

            DB::commit();

            return redirect()
                ->route('manufaktur.spk.show', $manufacturingOrder)
                ->with('success', "Surat Perintah Kerja (SPK) {$manufacturingOrder->mo_number} berhasil diperbarui.");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui SPK: ' . $e->getMessage());
        }
    }

    /**
     * Memulai proses produksi (Status: in_progress).
     */
    public function start(ManufacturingOrder $manufacturingOrder): RedirectResponse
    {
        if ($manufacturingOrder->status !== 'draft') {
            return redirect()
                ->back()
                ->with('error', 'Hanya SPK berstatus Draft yang dapat dimulai.');
        }

        if (! $this->manufacturingService->checkStockMaterial($manufacturingOrder)) {
            return redirect()
                ->back()
                ->with('error', 'Stok bahan baku di gudang tidak mencukupi untuk memulai SPK ini.');
        }

        DB::beginTransaction();
        try {
            $manufacturingOrder->status = 'in_progress';
            if (!$manufacturingOrder->start_date) {
                $manufacturingOrder->start_date = now();
            }
            $manufacturingOrder->save();

            if ($manufacturingOrder->productionPlan) {
                $manufacturingOrder->productionPlan->update(['status' => 'in_progress']);
            }

            DB::commit();

            return redirect()
                ->back()
                ->with('success', "SPK {$manufacturingOrder->mo_number} telah dimulai (In Progress). Tahap berikutnya: Lakukan pengeluaran bahan baku (Material Issue) ke lantai produksi.");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal memulai produksi: ' . $e->getMessage());
        }
    }

    /**
     * Membatalkan SPK (Status: cancelled).
     */
    public function cancel(Request $request, ManufacturingOrder $manufacturingOrder): RedirectResponse
    {
        if ($manufacturingOrder->status === 'completed') {
            return redirect()
                ->back()
                ->with('error', 'SPK yang sudah selesai tidak dapat dibatalkan.');
        }

        DB::beginTransaction();
        try {
            $moNumber = $manufacturingOrder->mo_number;

            if ($manufacturingOrder->productionPlan) {
                $manufacturingOrder->productionPlan->update(['status' => 'cancelled']);
            }

            $manufacturingOrder->delete();

            DB::commit();

            return redirect()
                ->route('manufaktur.spk.index')
                ->with('success', "SPK {$moNumber} telah dibatalkan.");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal membatalkan SPK: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus SPK (hanya jika draft atau cancelled).
     */
    public function destroy(ManufacturingOrder $manufacturingOrder): RedirectResponse
    {
        if (!in_array($manufacturingOrder->status, ['draft', 'cancelled'])) {
            return redirect()
                ->back()
                ->with('error', 'SPK yang sedang berjalan atau sudah selesai tidak dapat dihapus.');
        }

        try {
            $moNum = $manufacturingOrder->mo_number;
            $manufacturingOrder->delete();

            return redirect()
                ->route('manufaktur.spk.index')
                ->with('success', "SPK {$moNum} berhasil dihapus.");
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Gagal menghapus SPK: ' . $e->getMessage());
        }
    }

    /**
     * Cetak Lembar Instruksi Kerja SPK (PDF).
     */
    public function pdf(ManufacturingOrder $manufacturingOrder): Response
    {
        $manufacturingOrder->load([
            'productionPlan.product.uom',
            'productionPlan.billOfMaterial.items.product.uom',
            'productionPlan.warehouse',
            'cabang',
        ]);

        $plan = $manufacturingOrder->productionPlan;
        $bom = $plan?->billOfMaterial;
        $warehouse = $plan?->warehouse ?? Warehouse::first();

        $pdf = Pdf::loadView('manufaktur.spk.pdf', compact('manufacturingOrder', 'plan', 'bom', 'warehouse'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("SPK_{$manufacturingOrder->mo_number}.pdf");
    }

    /**
     * API: Ambil info BOM beserta bahan baku dan cek stok gudang.
     */
    public function bomDetails(BillOfMaterial $billOfMaterial, Request $request): JsonResponse
    {
        $billOfMaterial->load(['product.uom', 'uom', 'items.product.uom', 'items.uom']);
        $warehouseId = $request->query('warehouse_id');
        $targetQty = (float) $request->query('quantity', $billOfMaterial->quantity ?: 1);
        $baseQty = (float) ($billOfMaterial->quantity > 0 ? $billOfMaterial->quantity : 1);
        $ratio = $targetQty / $baseQty;

        $allMaterialsSufficient = true;

        $itemsWithStock = $billOfMaterial->items->map(function ($item) use ($warehouseId, $ratio, &$allMaterialsSufficient) {
            $requiredQty = (float) $item->quantity * $ratio;
            $currentStock = 0;
            if ($warehouseId) {
                $stock = InventoryStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                $currentStock = $stock ? max(0, (float) $stock->qty_available - (float) $stock->qty_reserved) : 0;
            }

            $isSufficient = $currentStock >= $requiredQty;
            if (! $isSufficient) {
                $allMaterialsSufficient = false;
            }

            return [
                'product_id' => $item->product_id,
                'product_sku' => $item->product?->sku ?? '-',
                'product_name' => $item->product?->name ?? 'Unknown',
                'uom_id' => $item->uom_id,
                'uom_name' => $item->uom?->name ?? 'pcs',
                'quantity' => (float) $item->quantity,
                'required_quantity' => $requiredQty,
                'available_stock' => $currentStock,
                'free_stock' => $currentStock,
                'is_sufficient' => $isSufficient,
            ];
        });

        return response()->json([
            'status' => true,
            'id' => $billOfMaterial->id,
            'code' => $billOfMaterial->code,
            'nama_bom' => $billOfMaterial->nama_bom,
            'product_id' => $billOfMaterial->product_id,
            'product_name' => $billOfMaterial->product?->name,
            'product_sku' => $billOfMaterial->product?->sku,
            'uom_id' => $billOfMaterial->uom_id,
            'uom_name' => $billOfMaterial->uom?->name,
            'base_quantity' => (float) $billOfMaterial->quantity,
            'target_quantity' => $targetQty,
            'all_materials_sufficient' => $allMaterialsSufficient,
            'items' => $itemsWithStock,
        ]);
    }
}
