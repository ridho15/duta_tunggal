<?php

namespace App\Http\Controllers\Manufaktur;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manufaktur\StoreMaterialIssueRequest;
use App\Http\Requests\Manufaktur\UpdateMaterialIssueRequest;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Services\ManufacturingJournalService;
use App\Services\ManufacturingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MaterialIssueController extends Controller
{
    protected ManufacturingService $manufacturingService;

    public function __construct(ManufacturingService $manufacturingService)
    {
        $this->manufacturingService = $manufacturingService;
    }

    /**
     * Menampilkan daftar Pengeluaran & Retur Bahan Baku.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $type = $request->query('type');
        $warehouseId = $request->query('warehouse_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $baseQuery = MaterialIssue::with([
            'manufacturingOrder.productionPlan.product',
            'productionPlan.product',
            'warehouse',
            'createdBy',
            'approvedBy',
            'items.product.uom',
        ]);

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->whereHas('warehouse', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        if ($warehouseId) {
            $baseQuery->where('warehouse_id', $warehouseId);
        }
        if ($type) {
            $baseQuery->where('type', $type);
        }
        if ($startDate) {
            $baseQuery->whereDate('issue_date', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('issue_date', '<=', $endDate);
        }

        // Hitung KPIs
        $totalCount = (clone $baseQuery)->count();
        $issueCount = (clone $baseQuery)->where('type', 'issue')->count();
        $returnCount = (clone $baseQuery)->where('type', 'return')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending_approval')->count();
        $completedCount = (clone $baseQuery)->where('status', 'completed')->count();
        $totalBdpCost = (clone $baseQuery)->where('type', 'issue')->where('status', 'completed')->sum('total_cost');

        // Filter tab
        $query = clone $baseQuery;
        if ($tab === 'issue') {
            $query->where('type', 'issue');
        } elseif ($tab === 'return') {
            $query->where('type', 'return');
        } elseif ($tab === 'draft') {
            $query->where('status', 'draft');
        } elseif ($tab === 'pending') {
            $query->where('status', 'pending_approval');
        } elseif ($tab === 'approved') {
            $query->where('status', 'approved');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        } elseif ($tab === 'rejected') {
            $query->where('status', 'rejected');
        }

        // Pencarian teks
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('issue_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('manufacturingOrder', function ($moQ) use ($search) {
                        $moQ->where('mo_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('productionPlan', function ($ppQ) use ($search) {
                        $ppQ->where('plan_number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.product', function ($prodQ) use ($search) {
                        $prodQ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $materialIssues = $query->latest('id')->paginate(15)->withQueryString();

        $warehouses = Warehouse::where('status', true);
        if (!$canManageAll && $user?->cabang_id) {
            $warehouses->where('cabang_id', $user->cabang_id);
        }
        $warehouses = $warehouses->orderBy('name')->get();

        return view('manufaktur.material_issue.index', compact(
            'materialIssues',
            'tab',
            'search',
            'type',
            'warehouseId',
            'startDate',
            'endDate',
            'totalCount',
            'issueCount',
            'returnCount',
            'pendingCount',
            'completedCount',
            'totalBdpCost',
            'warehouses'
        ));
    }

    /**
     * Menampilkan form pembuatan Pengeluaran / Retur Bahan Baku.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $warehouses = Warehouse::where('status', true);
        if (!$canManageAll && $user?->cabang_id) {
            $warehouses->where('cabang_id', $user->cabang_id);
        }
        $warehouses = $warehouses->orderBy('name')->get();

        // SPK aktif
        $manufacturingOrders = ManufacturingOrder::with([
            'productionPlan.product.uom',
            'productionPlan.billOfMaterial.items.product.uom',
            'productionPlan.warehouse',
        ])
            ->whereIn('status', ['draft', 'in_progress'])
            ->latest('id')
            ->get();

        // Bahan baku
        $rawMaterials = Product::where('is_raw_material', true)
            ->where('is_active', true)
            ->with('uom')
            ->orderBy('name')
            ->get();

        $selectedSpkId = $request->query('spk_id') ?? $request->query('mo_id');
        $preselectedOrder = null;
        if ($selectedSpkId) {
            $preselectedOrder = ManufacturingOrder::with([
                'productionPlan.product.uom',
                'productionPlan.billOfMaterial.items.product.uom',
                'warehouse',
            ])->find($selectedSpkId);
        }

        $suggestedIssueNumber = $this->manufacturingService->generateIssueNumber('issue');

        return view('manufaktur.material_issue.create', compact(
            'warehouses',
            'manufacturingOrders',
            'rawMaterials',
            'preselectedOrder',
            'suggestedIssueNumber'
        ));
    }

    /**
     * Menyimpan Pengeluaran / Retur Bahan Baku ke database.
     */
    public function store(StoreMaterialIssueRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $materialIssue = MaterialIssue::create([
                'type' => $validated['type'],
                'issue_number' => $validated['issue_number'],
                'manufacturing_order_id' => $validated['manufacturing_order_id'] ?? null,
                'production_plan_id' => $validated['production_plan_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'issue_date' => $validated['issue_date'],
                'status' => MaterialIssue::STATUS_DRAFT,
                'total_cost' => 0,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $totalCost = 0;

            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity'];
                $unitCost = isset($item['cost_per_unit']) && $item['cost_per_unit'] !== '' ? (float) $item['cost_per_unit'] : 0;

                // Jika harga per unit 0, coba fallback dari product cost_price
                if ($unitCost <= 0) {
                    $product = Product::find($item['product_id']);
                    $unitCost = (float) ($product->cost_price ?? 0);
                }

                $itemTotalCost = $qty * $unitCost;
                $totalCost += $itemTotalCost;

                MaterialIssueItem::create([
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? $validated['warehouse_id'],
                    'rak_id' => $item['rak_id'] ?? null,
                    'quantity' => $qty,
                    'cost_per_unit' => $unitCost,
                    'total_cost' => $itemTotalCost,
                    'notes' => $item['notes'] ?? null,
                    'status' => MaterialIssueItem::STATUS_DRAFT,
                ]);
            }

            $materialIssue->total_cost = $totalCost;
            $materialIssue->saveQuietly();

            // Jika user memilih tombol "Simpan & Ajukan ke Gudang"
            if ($request->input('action') === 'submit_approval') {
                $this->processSubmitApproval($materialIssue);
            }

            DB::commit();

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('success', "Dokumen {$materialIssue->issue_number} berhasil dibuat.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal menyimpan Material Issue: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail Pengeluaran / Retur Bahan Baku.
     */
    public function show(MaterialIssue $materialIssue): View
    {
        $materialIssue->load([
            'manufacturingOrder.productionPlan.product.uom',
            'productionPlan.product.uom',
            'warehouse',
            'createdBy',
            'approvedBy',
            'items.product.uom',
            'items.warehouse',
            'items.rak',
            'warehouseConfirmations.warehouseConfirmationItems.product',
            'warehouseConfirmations.user',
            'journalEntries.chartOfAccount',
        ]);

        // Cek ketersediaan stok fisik real-time per item
        $stockCheck = [];
        foreach ($materialIssue->items as $item) {
            $whId = $item->warehouse_id ?: $materialIssue->warehouse_id;
            $stock = InventoryStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $whId)
                ->first();

            $physical = (float) ($stock->qty_available ?? 0);
            $reserved = (float) ($stock->qty_reserved ?? 0);
            $ownReserved = (float) StockReservation::where('material_issue_id', $materialIssue->id)
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $whId)
                ->sum('quantity');

            $free = max(0, $physical - $reserved);
            $effective = max(0, $physical - $reserved + $ownReserved);

            $stockCheck[$item->id] = [
                'physical' => $physical,
                'reserved' => $reserved,
                'free' => $free,
                'effective' => $effective,
                'is_sufficient' => $materialIssue->type === 'return' || ($effective >= (float) $item->quantity),
            ];
        }

        $allStockSufficient = collect($stockCheck)->every(fn ($sc) => $sc['is_sufficient']);

        return view('manufaktur.material_issue.show', compact('materialIssue', 'stockCheck', 'allStockSufficient'));
    }

    /**
     * Menampilkan form edit Pengeluaran / Retur Bahan Baku.
     */
    public function edit(MaterialIssue $materialIssue): View|RedirectResponse
    {
        if ($materialIssue->status !== MaterialIssue::STATUS_DRAFT) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', "Dokumen tidak dapat diubah karena sudah berstatus {$materialIssue->status}.");
        }

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $warehouses = Warehouse::where('status', true);
        if (!$canManageAll && $user?->cabang_id) {
            $warehouses->where('cabang_id', $user->cabang_id);
        }
        $warehouses = $warehouses->orderBy('name')->get();

        $manufacturingOrders = ManufacturingOrder::with([
            'productionPlan.product.uom',
            'productionPlan.warehouse',
        ])
            ->whereIn('status', ['draft', 'in_progress'])
            ->latest('id')
            ->get();

        $rawMaterials = Product::where('is_raw_material', true)
            ->where('is_active', true)
            ->with('uom')
            ->orderBy('name')
            ->get();

        $materialIssue->load(['items.product.uom', 'manufacturingOrder']);

        return view('manufaktur.material_issue.edit', compact(
            'materialIssue',
            'warehouses',
            'manufacturingOrders',
            'rawMaterials'
        ));
    }

    /**
     * Memperbarui dokumen Pengeluaran / Retur Bahan Baku.
     */
    public function update(UpdateMaterialIssueRequest $request, MaterialIssue $materialIssue): RedirectResponse
    {
        if ($materialIssue->status !== MaterialIssue::STATUS_DRAFT) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', "Dokumen tidak dapat diubah karena sudah berstatus {$materialIssue->status}.");
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $materialIssue->update([
                'type' => $validated['type'],
                'issue_number' => $validated['issue_number'],
                'manufacturing_order_id' => $validated['manufacturing_order_id'] ?? null,
                'production_plan_id' => $validated['production_plan_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'issue_date' => $validated['issue_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Hapus item lama dan buat baru
            $materialIssue->items()->delete();

            $totalCost = 0;
            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity'];
                $unitCost = isset($item['cost_per_unit']) && $item['cost_per_unit'] !== '' ? (float) $item['cost_per_unit'] : 0;

                if ($unitCost <= 0) {
                    $product = Product::find($item['product_id']);
                    $unitCost = (float) ($product->cost_price ?? 0);
                }

                $itemTotalCost = $qty * $unitCost;
                $totalCost += $itemTotalCost;

                MaterialIssueItem::create([
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? $validated['warehouse_id'],
                    'rak_id' => $item['rak_id'] ?? null,
                    'quantity' => $qty,
                    'cost_per_unit' => $unitCost,
                    'total_cost' => $itemTotalCost,
                    'notes' => $item['notes'] ?? null,
                    'status' => MaterialIssueItem::STATUS_DRAFT,
                ]);
            }

            $materialIssue->total_cost = $totalCost;
            $materialIssue->saveQuietly();

            if ($request->input('action') === 'submit_approval') {
                $this->processSubmitApproval($materialIssue);
            }

            DB::commit();

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('success', "Dokumen {$materialIssue->issue_number} berhasil diperbarui.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui Material Issue: ' . $e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memperbarui dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus dokumen Pengeluaran / Retur Bahan Baku.
     */
    public function destroy(MaterialIssue $materialIssue): RedirectResponse
    {
        if ($materialIssue->status !== MaterialIssue::STATUS_DRAFT) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', "Dokumen tidak dapat dihapus karena sudah berstatus {$materialIssue->status}.");
        }

        try {
            $materialIssue->delete();

            return redirect()
                ->route('manufaktur.material-issue.index')
                ->with('success', "Dokumen {$materialIssue->issue_number} berhasil dihapus.");
        } catch (Throwable $e) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', 'Gagal menghapus dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Mengajukan Pengeluaran / Retur Bahan Baku ke Gudang.
     */
    public function submitApproval(MaterialIssue $materialIssue): RedirectResponse
    {
        if ($materialIssue->status !== MaterialIssue::STATUS_DRAFT) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', 'Hanya dokumen berstatus Draft yang dapat diajukan ke Gudang.');
        }

        // Cek ketersediaan stok jika tipe issue
        if ($materialIssue->type === 'issue') {
            $stockValidation = $this->validateStockAvailability($materialIssue);
            if (!$stockValidation['valid']) {
                return redirect()
                    ->route('manufaktur.material-issue.show', $materialIssue)
                    ->with('error', $stockValidation['message']);
            }
        }

        try {
            $this->processSubmitApproval($materialIssue);

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('success', "Dokumen {$materialIssue->issue_number} berhasil diajukan ke Gudang.");
        } catch (Throwable $e) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', 'Gagal mengajukan dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Menyetujui konfirmasi gudang secara langsung.
     */
    public function confirmWarehouse(Request $request, MaterialIssue $materialIssue): RedirectResponse
    {
        if (!in_array($materialIssue->status, [MaterialIssue::STATUS_DRAFT, MaterialIssue::STATUS_PENDING_APPROVAL])) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', "Dokumen berstatus {$materialIssue->status} tidak dapat dikonfirmasi.");
        }

        try {
            DB::beginTransaction();

            // Pastikan konfirmasi gudang ada
            $materialIssue->ensureWarehouseConfirmationRequest();

            // Ambil seluruh konfirmasi gudang dan setujui
            $confirmations = $materialIssue->warehouseConfirmations()->get();
            foreach ($confirmations as $wc) {
                $wc->update([
                    'status' => 'confirmed',
                    'confirmed_by' => Auth::id(),
                    'confirmed_at' => now(),
                ]);
            }

            // Panggil sinkronisasi approve
            $lastWc = $confirmations->last();
            if ($lastWc) {
                $materialIssue->approveFromWarehouseConfirmation($lastWc);
            }

            DB::commit();

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('success', "Konfirmasi Gudang untuk dokumen {$materialIssue->issue_number} berhasil disetujui.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal konfirmasi gudang: ' . $e->getMessage());

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', 'Gagal memproses konfirmasi gudang: ' . $e->getMessage());
        }
    }

    /**
     * Menolak pengeluaran / retur bahan baku.
     */
    public function reject(Request $request, MaterialIssue $materialIssue): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $reason = $request->input('rejection_reason');

            // Tandai konfirmasi gudang sebagai rejected jika ada
            foreach ($materialIssue->warehouseConfirmations as $wc) {
                $wc->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'confirmed_by' => Auth::id(),
                    'confirmed_at' => now(),
                ]);
            }

            $notes = trim(($materialIssue->notes ? $materialIssue->notes . "\n\n" : '') . "DITOLAK GUDANG: {$reason} (" . now()->format('d/m/Y H:i') . ')');

            $materialIssue->update([
                'status' => MaterialIssue::STATUS_REJECTED,
                'approved_by' => null,
                'approved_at' => null,
                'notes' => $notes,
            ]);

            DB::commit();

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('warning', "Dokumen {$materialIssue->issue_number} telah ditolak.");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', 'Gagal menolak dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Menyelesaikan pengeluaran bahan dan memicu jurnal akuntansi jika belum.
     */
    public function complete(MaterialIssue $materialIssue): RedirectResponse
    {
        if ($materialIssue->status === MaterialIssue::STATUS_COMPLETED) {
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('info', 'Dokumen ini sudah berstatus Selesai.');
        }

        try {
            DB::beginTransaction();

            // Pastikan konfirmasi gudang confirmed agar validation hook tidak memblokir
            $materialIssue->ensureWarehouseConfirmationRequest();
            foreach ($materialIssue->warehouseConfirmations()->get() as $wc) {
                if ($wc->status !== 'confirmed') {
                    $wc->update([
                        'status' => 'confirmed',
                        'confirmed_by' => Auth::id(),
                        'confirmed_at' => now(),
                    ]);
                }
            }

            // Set approved by jika belum
            if (!$materialIssue->approved_by) {
                $materialIssue->approved_by = Auth::id();
                $materialIssue->approved_at = now();
            }

            $materialIssue->status = MaterialIssue::STATUS_COMPLETED;
            $materialIssue->save();

            DB::commit();

            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('success', "Dokumen {$materialIssue->issue_number} berhasil diselesaikan. Stok fisik dan Jurnal BDP telah dimutasi.");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->route('manufaktur.material-issue.show', $materialIssue)
                ->with('error', 'Gagal menyelesaikan dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Cetak PDF Bukti Pengeluaran / Retur Bahan Baku.
     */
    public function pdf(MaterialIssue $materialIssue): Response
    {
        $materialIssue->load([
            'manufacturingOrder.productionPlan.product.uom',
            'productionPlan.product.uom',
            'warehouse.cabang',
            'createdBy',
            'approvedBy',
            'items.product.uom',
            'items.warehouse',
            'items.rak',
        ]);

        $pdf = Pdf::loadView('manufaktur.material_issue.pdf', compact('materialIssue'))
            ->setPaper('a4', 'portrait');

        $filename = "Bukti-{$materialIssue->issue_number}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * API JSON: Mengambil bahan BOM dan stok bebas untuk SPK tertentu.
     */
    public function apiSpkMaterials(ManufacturingOrder $manufacturingOrder): JsonResponse
    {
        $manufacturingOrder->load([
            'productionPlan.product.uom',
            'productionPlan.billOfMaterial.items.product.uom',
            'productionPlan.warehouse',
        ]);

        $productionPlan = $manufacturingOrder->productionPlan;
        $bom = $productionPlan?->billOfMaterial;

        if (!$bom) {
            return response()->json([
                'success' => false,
                'message' => 'Resep BOM tidak ditemukan untuk SPK ini.',
                'items' => [],
            ], 404);
        }

        $multiplier = (float) ($productionPlan->quantity ?? 1);
        $defaultWhId = $manufacturingOrder->warehouse_id ?? $productionPlan->warehouse_id;

        $items = [];
        foreach ($bom->items as $bomItem) {
            $requiredQty = (float) $bomItem->quantity * $multiplier;
            $unitCost = (float) ($bomItem->product->cost_price ?? 0);
            $subtotal = $requiredQty * $unitCost;

            // Cek stok bebas di gudang default
            $stock = InventoryStock::where('product_id', $bomItem->product_id)
                ->where('warehouse_id', $defaultWhId)
                ->first();

            $freeStock = $stock ? max(0, (float) $stock->qty_available - (float) $stock->qty_reserved) : 0;

            $items[] = [
                'product_id' => $bomItem->product_id,
                'product_name' => $bomItem->product->name,
                'product_sku' => $bomItem->product->sku,
                'uom_id' => $bomItem->uom_id ?: $bomItem->product->uom_id,
                'uom_name' => $bomItem->uom?->name ?? $bomItem->product->uom?->name ?? 'Pcs',
                'quantity' => $requiredQty,
                'cost_per_unit' => $unitCost,
                'total_cost' => $subtotal,
                'warehouse_id' => $defaultWhId,
                'warehouse_name' => $manufacturingOrder->warehouse?->name ?? 'Gudang Utama',
                'rak_id' => null,
                'free_stock' => $freeStock,
                'is_stock_sufficient' => $freeStock >= $requiredQty,
            ];
        }

        return response()->json([
            'success' => true,
            'mo_id' => $manufacturingOrder->id,
            'mo_number' => $manufacturingOrder->mo_number,
            'production_plan_id' => $productionPlan?->id,
            'product_name' => $productionPlan?->product?->name,
            'target_quantity' => $multiplier,
            'warehouse_id' => $defaultWhId,
            'items' => $items,
        ]);
    }

    /**
     * API JSON: Mengambil detail produk (UOM, cost price, stok bebas).
     */
    public function apiProductDetails(Product $product, Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        $freeStock = 0;
        if ($warehouseId) {
            $stock = InventoryStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->first();
            $freeStock = $stock ? max(0, (float) $stock->qty_available - (float) $stock->qty_reserved) : 0;
        }

        return response()->json([
            'success' => true,
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'uom_id' => $product->uom_id,
            'uom_name' => $product->uom?->name ?? 'Pcs',
            'cost_price' => (float) ($product->cost_price ?? 0),
            'free_stock' => $freeStock,
        ]);
    }

    /**
     * Helper privat: Inisiasi pengajuan approval ke gudang.
     */
    protected function processSubmitApproval(MaterialIssue $materialIssue): void
    {
        $materialIssue->ensureWarehouseConfirmationRequest();

        if ($materialIssue->hasConfirmedWarehouseConfirmation()) {
            $materialIssue->approveFromWarehouseConfirmation(
                $materialIssue->latestWarehouseConfirmation()
            );
        } else {
            $materialIssue->update([
                'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }
    }

    /**
     * Helper privat: Memvalidasi ketersediaan stok fisik bebas.
     */
    protected function validateStockAvailability(MaterialIssue $materialIssue): array
    {
        $materialIssue->loadMissing('items.product');

        $insufficient = [];

        foreach ($materialIssue->items as $item) {
            $whId = $item->warehouse_id ?: $materialIssue->warehouse_id;
            $stock = InventoryStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $whId)
                ->first();

            $physical = (float) ($stock->qty_available ?? 0);
            $reserved = (float) ($stock->qty_reserved ?? 0);
            $ownReserved = (float) StockReservation::where('material_issue_id', $materialIssue->id)
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $whId)
                ->sum('quantity');

            $effective = max(0, $physical - $reserved + $ownReserved);

            if ($effective < (float) $item->quantity) {
                $insufficient[] = "{$item->product->name} (Dibutuhkan: {$item->quantity}, Bebas: {$effective})";
            }
        }

        if (!empty($insufficient)) {
            return [
                'valid' => false,
                'message' => 'Stok bebas tidak mencukupi untuk bahan: ' . implode(', ', $insufficient),
            ];
        }

        return ['valid' => true];
    }
}
