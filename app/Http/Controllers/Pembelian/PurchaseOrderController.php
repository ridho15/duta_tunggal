<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HelperController;
use App\Http\Requests\Pembelian\StorePurchaseOrderRequest;
use App\Http\Requests\Pembelian\UpdatePurchaseOrderRequest;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Support\TaxTypeHelper;
use App\Support\CurrencyConversionResolver;
use App\Support\OrderRequestQuantityLock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class PurchaseOrderController extends Controller
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Tampilkan daftar Pesanan Pembelian (Purchase Order / PO)
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $cabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('search', $request->query('q', '')));
        $supplierFilter = $request->query('supplier_id');
        $cabangFilter = $request->query('cabang_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Base query with branch scoping
        $baseQuery = PurchaseOrder::query();
        if (!$canAccessAll && $cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Hitung count per status untuk tab filter secara efisien
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'menunggu_approve' => (clone $baseQuery)->where('status', 'request_approve')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'partially_received' => (clone $baseQuery)->where('status', 'partially_received')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'closed' => (clone $baseQuery)->whereIn('status', ['closed', 'cancelled', 'canceled'])->count(),
        ];

        // Hitung 4 KPI utama
        $kpiQuery = clone $baseQuery;
        $kpi = [
            'total_po_amount' => (float) (clone $kpiQuery)->sum('total_amount'),
            'draft_count' => $counts['draft'],
            'active_or_approved_count' => (clone $kpiQuery)->whereIn('status', ['request_approve', 'approved', 'partially_received'])->count(),
            'completed_count' => $counts['completed'],
        ];

        // Filter query menurut tab yang aktif
        $query = clone $baseQuery;
        match ($activeTab) {
            'draft' => $query->where('status', 'draft'),
            'menunggu_approve' => $query->where('status', 'request_approve'),
            'approved' => $query->where('status', 'approved'),
            'partially_received' => $query->where('status', 'partially_received'),
            'completed' => $query->where('status', 'completed'),
            'closed' => $query->whereIn('status', ['closed', 'cancelled', 'canceled']),
            default => $query,
        };

        // Filter Cabang (jika user punya wewenang melihat semua cabang)
        if (!empty($cabangFilter) && $canAccessAll) {
            $query->where('cabang_id', $cabangFilter);
        }

        // Filter Supplier
        if (!empty($supplierFilter)) {
            $query->where('supplier_id', $supplierFilter);
        }

        // Filter Tanggal Order
        if (!empty($dateFrom)) {
            $query->whereDate('order_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('order_date', '<=', $dateTo);
        }

        // Pencarian teks: nomor PO, supplier, atau catatan
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('perusahaan', 'like', "%{$search}%")
                            ->orWhere('kontak_person', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Paginasi data dengan mempertahankan parameter URL query string
        $purchaseOrders = $query
            ->with([
                'supplier',
                'cabang',
                'warehouse',
                'approvedBy',
                'createdBy',
                'purchaseOrderItem.purchaseReceiptItem',
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Data master untuk dropdown filter
        $suppliers = Supplier::orderBy('perusahaan')->get(['id', 'perusahaan', 'code']);
        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama']);

        return view('pembelian.po.index', compact(
            'purchaseOrders',
            'counts',
            'kpi',
            'activeTab',
            'search',
            'suppliers',
            'cabangs',
            'supplierFilter',
            'cabangFilter',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Tampilkan formulir pembuatan Pesanan Pembelian baru
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $defaultCabangId = $user?->cabang_id ?? Cabang::first()?->id;

        $suppliers = Supplier::orderBy('perusahaan')->get([
            'id', 'perusahaan', 'code', 'tempo_hutang', 'phone', 'address', 'kontak_person'
        ]);
        $warehouses = Warehouse::where('status', 'active')->orWhereNull('status')->orderBy('name')->get(['id', 'name', 'kode']);
        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama', 'kode']);
        $currencies = Currency::orderBy('code')->get(['id', 'code', 'name', 'symbol', 'to_rupiah']);

        $defaultPoNumber = HelperController::generatePoNumber();
        $defaultDate = Carbon::now()->format('Y-m-d');
        $defaultCurrency = Currency::where('code', 'IDR')->first() ?? $currencies->first();

        $selectedSupplierId = $request->query('supplier_id');
        $selectedSupplier = $selectedSupplierId ? Supplier::find($selectedSupplierId) : null;

        $selectedOrderRequestId = $request->query('order_request_id');
        $selectedOrderRequest = null;
        $initialPoItems = [];
        $preloadedProducts = collect([]);

        if ($selectedOrderRequestId) {
            $selectedOrderRequest = OrderRequest::with([
                'orderRequestItem.product.uom',
                'orderRequestItem.supplier',
            ])->find($selectedOrderRequestId);

            if ($selectedOrderRequest) {
                if ($selectedOrderRequest->cabang_id) {
                    $defaultCabangId = $selectedOrderRequest->cabang_id;
                }

                $orProductIds = $selectedOrderRequest->orderRequestItem->pluck('product_id')->filter()->unique();
                $preloadedProducts = Product::with([
                    'uom:id,name,abbreviation',
                    'suppliers' => function ($q) {
                        $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')->withPivot('supplier_price');
                    }
                ])->whereIn('id', $orProductIds)->get(['id', 'name', 'sku', 'cost_price', 'uom_id', 'cabang_id', 'pajak', 'tipe_pajak']);

                foreach ($selectedOrderRequest->orderRequestItem as $orItem) {
                    $limit = OrderRequestQuantityLock::orderRequestItemLimit($orItem->id);
                    $remainingQty = (float) $limit['remaining_for_po'];

                    if ($remainingQty > 0 || (float)($orItem->fulfilled_quantity ?? 0) === 0.0) {
                        $qty = $remainingQty > 0 ? $remainingQty : (float)$orItem->quantity;
                        $initialPoItems[] = [
                            'product_id' => $orItem->product_id,
                            'order_request_item_id' => $orItem->id,
                            'product_name' => $orItem->product?->name ?? 'Produk #' . $orItem->product_id,
                            'sku' => $orItem->product?->sku ?? '-',
                            'uom' => $orItem->product?->uom?->abbreviation ?? $orItem->product?->uom?->name ?? 'Pcs',
                            'quantity' => $qty,
                            'max_quantity' => $remainingQty > 0 ? $remainingQty : (float)$orItem->quantity,
                            'unit_price' => (float)($orItem->unit_price > 0 ? $orItem->unit_price : ($orItem->product?->cost_price ?? 0)),
                            'discount' => (float)($orItem->discount ?? 0),
                            'tipe_pajak' => $orItem->tipe_pajak ?? 'eklusif',
                            'tax' => (float)($orItem->tax > 0 ? $orItem->tax : 11),
                            'currency_id' => $orItem->currency_id ?? $defaultCurrency->id,
                        ];
                    }
                }

                if (!$selectedSupplier && $selectedOrderRequest->orderRequestItem->first()?->supplier_id) {
                    $selectedSupplier = $selectedOrderRequest->orderRequestItem->first()->supplier;
                }
            }
        }

        $products = $preloadedProducts;

        return view('pembelian.po.create', compact(
            'suppliers',
            'warehouses',
            'cabangs',
            'currencies',
            'defaultPoNumber',
            'defaultDate',
            'defaultCabangId',
            'defaultCurrency',
            'selectedSupplier',
            'selectedOrderRequest',
            'initialPoItems',
            'preloadedProducts',
            'products'
        ));
    }

    /**
     * Simpan transaksi Pesanan Pembelian baru ke basis data
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $cabangId = !empty($validated['cabang_id']) ? (int) $validated['cabang_id'] : ($user?->cabang_id ?? Cabang::first()?->id ?? 1);
        $poNumber = !empty($validated['po_number']) ? trim($validated['po_number']) : HelperController::generatePoNumber();

        // Pastikan nomor PO unik; jika duplikat atau bentrok, generate baru
        if (PurchaseOrder::withoutGlobalScopes()->where('po_number', $poNumber)->exists()) {
            $poNumber = HelperController::generatePoNumber();
        }

        // Tentukan status awal berdasarkan aksi tombol form
        $action = $validated['action'] ?? 'save_draft';
        $status = match ($action) {
            'save_and_approve' => 'approved',
            'save_and_request' => 'request_approve',
            default => 'draft',
        };

        try {
            $po = DB::transaction(function () use ($validated, $cabangId, $poNumber, $status, $user) {
                $orderRequestId = !empty($validated['order_request_id']) ? (int) $validated['order_request_id'] : null;

                $poRecord = PurchaseOrder::create([
                    'supplier_id' => $validated['supplier_id'],
                    'cabang_id' => $cabangId,
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'po_number' => $poNumber,
                    'order_date' => $validated['order_date'],
                    'expected_date' => $validated['expected_date'] ?? null,
                    'top_type' => $validated['top_type'] ?? 'cod',
                    'tempo_hutang' => (int) ($validated['tempo_hutang'] ?? 0),
                    'is_import' => (bool) ($validated['is_import'] ?? false),
                    'is_asset' => (bool) ($validated['is_asset'] ?? false),
                    'note' => $validated['note'] ?? null,
                    'status' => $status,
                    'created_by' => $user?->id ?? 1,
                    'total_amount' => 0,
                    'date_approved' => $status === 'approved' ? Carbon::now() : null,
                    'approved_by' => $status === 'approved' ? ($user?->id ?? 1) : null,
                    'refer_model_type' => $orderRequestId ? OrderRequest::class : null,
                    'refer_model_id' => $orderRequestId,
                ]);

                // Simpan item-item PO
                foreach ($validated['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $discount = (float) ($item['discount'] ?? 0);
                    $tax = (float) ($item['tax'] ?? 0);
                    $tipePajak = TaxTypeHelper::normalize($item['tipe_pajak'] ?? 'eklusif');
                    $currencyId = !empty($item['currency_id']) ? (int) $item['currency_id'] : (!empty($validated['currency_id']) ? (int) $validated['currency_id'] : null);
                    $orItemId = !empty($item['order_request_item_id']) ? (int) $item['order_request_item_id'] : null;

                    $orItem = null;
                    if ($orItemId) {
                        $orItem = OrderRequestItem::find($orItemId);
                        if ($orItem) {
                            $limit = OrderRequestQuantityLock::orderRequestItemLimit($orItemId);
                            if ($qty > $limit['remaining_for_po']) {
                                throw new \Exception("Kuantitas produk '{$orItem->product?->name}' ({$qty}) melebihi sisa permintaan pada Order Request (Maks: {$limit['remaining_for_po']}).");
                            }
                        }
                    }

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $poRecord->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'original_unit_price' => $unitPrice,
                        'discount' => $discount,
                        'tax' => $tax,
                        'tipe_pajak' => $tipePajak,
                        'currency_id' => $currencyId,
                        'refer_item_model_type' => $orItemId ? OrderRequestItem::class : null,
                        'refer_item_model_id' => $orItemId,
                    ]);

                    if ($orItem) {
                        $orItem->addFulfilledQuantity($qty);
                    }
                }

                if ($orderRequestId) {
                    $orderRequest = OrderRequest::find($orderRequestId);
                    if ($orderRequest) {
                        $orderRequest->syncFulfillmentStatus();
                    }
                }

                // Simpan currency PO dari header dan per-item
                $headerCurrencyId = !empty($validated['currency_id']) ? (int) $validated['currency_id'] : null;
                $headerRate = !empty($validated['exchange_rate']) && (float) $validated['exchange_rate'] > 0 ? (float) $validated['exchange_rate'] : null;

                $allCurrencyIds = collect($validated['items'])
                    ->pluck('currency_id')
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->push($headerCurrencyId)
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($allCurrencyIds as $cId) {
                    $rate = ($cId === $headerCurrencyId && $headerRate)
                        ? $headerRate
                        : CurrencyConversionResolver::resolveRate($cId);

                    PurchaseOrderCurrency::create([
                        'purchase_order_id' => $poRecord->id,
                        'currency_id' => $cId,
                        'nominal' => $rate > 0 ? $rate : 1.0,
                    ]);
                }

                // Kalkulasi total amount PO
                $this->purchaseOrderService->updateTotalAmount($poRecord);

                return $poRecord;
            });

            $statusText = match ($status) {
                'approved' => 'dan langsung disetujui',
                'request_approve' => 'dan diajukan untuk persetujuan',
                default => 'sebagai draf',
            };

            return redirect()->route('pembelian.po.show', $po)
                ->with('success', "Pesanan Pembelian {$po->po_number} berhasil disimpan {$statusText}.");
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan Purchase Order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan pesanan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail lengkap transaksi Pesanan Pembelian
     */
    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->loadMissing([
            'supplier',
            'cabang',
            'warehouse',
            'createdBy',
            'approvedBy',
            'closeRequestedBy',
            'closedBy',
            'completedBy',
            'purchaseOrderItem.product.uom',
            'purchaseOrderItem.currency',
            'purchaseOrderItem.purchaseReceiptItem.purchaseReceipt',
            'purchaseOrderCurrency.currency',
            'purchaseReceipt.purchaseReceiptItem',
            'qualityControls.qualityControlItems',
            'invoice.invoiceItem',
        ]);

        $fulfillment = $purchaseOrder->receiptFulfillmentSummary();
        $totalSummary = $this->purchaseOrderService->calculateTotalSummary($purchaseOrder);

        return view('pembelian.po.show', compact('purchaseOrder', 'fulfillment', 'totalSummary'));
    }

    /**
     * Tampilkan formulir edit transaksi Pesanan Pembelian (hanya jika masih berstatus draft)
     */
    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('pembelian.po.show', $purchaseOrder)
                ->with('error', 'Hanya Pesanan Pembelian berstatus Draf yang dapat diubah.');
        }

        $purchaseOrder->loadMissing([
            'supplier',
            'supplier',
            'cabang',
            'warehouse',
            'purchaseOrderItem.product.uom',
            'purchaseOrderItem.currency',
            'purchaseOrderCurrency',
        ]);

        $suppliers = Supplier::orderBy('perusahaan')->get([
            'id', 'perusahaan', 'code', 'tempo_hutang', 'phone', 'address', 'kontak_person'
        ]);
        $warehouses = Warehouse::where('status', 'active')->orWhereNull('status')->orderBy('name')->get(['id', 'name', 'kode']);
        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama', 'kode']);
        $currencies = Currency::orderBy('code')->get(['id', 'code', 'name', 'symbol', 'to_rupiah']);

        $itemProductIds = $purchaseOrder->purchaseOrderItem->pluck('product_id')->filter()->unique();
        $preloadedProducts = Product::with([
            'uom:id,name,abbreviation',
            'suppliers' => function ($q) {
                $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')->withPivot('supplier_price');
            }
        ])->whereIn('id', $itemProductIds)->get(['id', 'name', 'sku', 'cost_price', 'uom_id', 'cabang_id', 'pajak', 'tipe_pajak']);

        $existingItems = $purchaseOrder->purchaseOrderItem->map(function ($item) {
            $maxQty = null;
            if ($item->refer_item_model_type === OrderRequestItem::class && $item->refer_item_model_id) {
                $limit = OrderRequestQuantityLock::orderRequestItemLimit($item->refer_item_model_id, $item->id);
                $maxQty = (float) ($item->quantity + $limit['remaining_for_po']);
            }
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'order_request_item_id' => $item->refer_item_model_type === OrderRequestItem::class ? $item->refer_item_model_id : null,
                'product_name' => $item->product?->name ?? 'Produk #' . $item->product_id,
                'sku' => $item->product?->sku ?? '-',
                'uom' => $item->product?->uom?->abbreviation ?? $item->product?->uom?->name ?? 'Pcs',
                'quantity' => (float)$item->quantity,
                'max_quantity' => $maxQty,
                'unit_price' => (float)$item->unit_price,
                'discount' => (float)$item->discount,
                'tax' => (float)$item->tax,
                'tipe_pajak' => $item->tipe_pajak ?? 'eklusif',
                'currency_id' => $item->currency_id,
            ];
        });

        return view('pembelian.po.edit', compact(
            'purchaseOrder',
            'suppliers',
            'warehouses',
            'cabangs',
            'currencies',
            'preloadedProducts',
            'existingItems'
        ));
    }

    /**
     * Simpan pembaruan data Pesanan Pembelian (hanya jika masih berstatus draft)
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('pembelian.po.show', $purchaseOrder)
                ->with('error', 'Hanya Pesanan Pembelian berstatus Draf yang dapat diubah.');
        }

        $validated = $request->validated();
        $user = Auth::user();

        $action = $validated['action'] ?? 'save_draft';
        $status = match ($action) {
            'save_and_approve' => 'approved',
            'save_and_request' => 'request_approve',
            default => 'draft',
        };

        try {
            DB::transaction(function () use ($validated, $purchaseOrder, $status, $user) {
                $purchaseOrder->update([
                    'supplier_id' => $validated['supplier_id'],
                    'cabang_id' => !empty($validated['cabang_id']) ? (int) $validated['cabang_id'] : $purchaseOrder->cabang_id,
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'po_number' => !empty($validated['po_number']) ? trim($validated['po_number']) : $purchaseOrder->po_number,
                    'order_date' => $validated['order_date'],
                    'expected_date' => $validated['expected_date'] ?? null,
                    'top_type' => $validated['top_type'] ?? 'cod',
                    'tempo_hutang' => (int) ($validated['tempo_hutang'] ?? 0),
                    'is_import' => (bool) ($validated['is_import'] ?? false),
                    'is_asset' => (bool) ($validated['is_asset'] ?? false),
                    'note' => $validated['note'] ?? null,
                    'status' => $status,
                    'date_approved' => $status === 'approved' ? Carbon::now() : null,
                    'approved_by' => $status === 'approved' ? ($user?->id ?? 1) : null,
                ]);

                // Kumpulkan affected OR item IDs sebelum delete untuk di-resync
                $affectedOrItemIds = $purchaseOrder->purchaseOrderItem()
                    ->where('refer_item_model_type', OrderRequestItem::class)
                    ->pluck('refer_item_model_id')
                    ->filter()
                    ->all();

                // Hapus item-item lama dan gantikan dengan item-item baru
                $purchaseOrder->purchaseOrderItem()->forceDelete();

                foreach ($validated['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $discount = (float) ($item['discount'] ?? 0);
                    $tax = (float) ($item['tax'] ?? 0);
                    $tipePajak = TaxTypeHelper::normalize($item['tipe_pajak'] ?? 'eklusif');
                    $currencyId = !empty($item['currency_id']) ? (int) $item['currency_id'] : (!empty($validated['currency_id']) ? (int) $validated['currency_id'] : null);
                    $orItemId = !empty($item['order_request_item_id']) ? (int) $item['order_request_item_id'] : null;

                    if ($orItemId) {
                        $affectedOrItemIds[] = $orItemId;
                    }

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'original_unit_price' => $unitPrice,
                        'discount' => $discount,
                        'tax' => $tax,
                        'tipe_pajak' => $tipePajak,
                        'currency_id' => $currencyId,
                        'refer_item_model_type' => $orItemId ? OrderRequestItem::class : null,
                        'refer_item_model_id' => $orItemId,
                    ]);
                }

                // Resync fulfillment untuk OR items yang terpengaruh
                $affectedOrIds = [];
                if ($purchaseOrder->refer_model_type === OrderRequest::class && $purchaseOrder->refer_model_id) {
                    $affectedOrIds[] = $purchaseOrder->refer_model_id;
                }
                foreach (array_unique($affectedOrItemIds) as $affectedOrItemId) {
                    $orItem = OrderRequestItem::find($affectedOrItemId);
                    if ($orItem) {
                        $totalActiveQty = PurchaseOrderItem::where('refer_item_model_type', OrderRequestItem::class)
                            ->where('refer_item_model_id', $affectedOrItemId)
                            ->whereHas('purchaseOrder', function($q) {
                                $q->whereNotIn('status', ['cancelled', 'rejected']);
                            })
                            ->sum('quantity');
                        $orItem->fulfilled_quantity = $totalActiveQty;
                        $orItem->save();
                        $affectedOrIds[] = $orItem->order_request_id;
                    }
                }
                foreach (array_unique(array_filter($affectedOrIds)) as $orId) {
                    $or = OrderRequest::find($orId);
                    $or?->syncFulfillmentStatus();
                }

                // Update currency PO dari header dan per-item
                $purchaseOrder->purchaseOrderCurrency()->delete();

                $headerCurrencyId = !empty($validated['currency_id']) ? (int) $validated['currency_id'] : null;
                $headerRate = !empty($validated['exchange_rate']) && (float) $validated['exchange_rate'] > 0 ? (float) $validated['exchange_rate'] : null;

                $allCurrencyIds = collect($validated['items'])
                    ->pluck('currency_id')
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->push($headerCurrencyId)
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($allCurrencyIds as $cId) {
                    $rate = ($cId === $headerCurrencyId && $headerRate)
                        ? $headerRate
                        : CurrencyConversionResolver::resolveRate($cId);

                    PurchaseOrderCurrency::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'currency_id' => $cId,
                        'nominal' => $rate > 0 ? $rate : 1.0,
                    ]);
                }

                // Kalkulasi ulang total amount
                $this->purchaseOrderService->updateTotalAmount($purchaseOrder);
            });

            return redirect()->route('pembelian.po.show', $purchaseOrder)
                ->with('success', "Pesanan Pembelian {$purchaseOrder->po_number} berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui Purchase Order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memperbarui pesanan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Setujui transaksi Pesanan Pembelian (Approve)
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (!in_array($purchaseOrder->status, ['draft', 'request_approve'])) {
            return back()->with('error', 'Hanya PO berstatus Draf atau Menunggu Persetujuan yang dapat disetujui.');
        }

        $approvalCheck = app(\App\Services\ApprovalControlService::class)->canApprovePurchaseOrder(Auth::user(), $purchaseOrder);
        if (! $approvalCheck['allowed']) {
            return back()->with('error', $approvalCheck['reason']);
        }

        try {
            DB::beginTransaction();
            $this->purchaseOrderService->approvePo($purchaseOrder, Auth::id());
            DB::commit();

            return back()->with('success', "Pesanan Pembelian {$purchaseOrder->po_number} berhasil disetujui.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal menyetujui Purchase Order', [
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Gagal menyetujui PO: ' . $e->getMessage());
        }
    }

    /**
     * Ajukan penutupan transaksi Pesanan Pembelian (Request Close)
     */
    public function requestClose(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan pengajuan penutupan PO wajib diisi.',
            'reason.max' => 'Alasan penutupan maksimal 500 karakter.',
        ]);

        if (!in_array($purchaseOrder->status, ['approved', 'partially_received'])) {
            return back()->with('error', 'Hanya PO yang sudah disetujui atau diterima sebagian yang dapat diajukan penutupan.');
        }

        $purchaseOrder->update([
            'status' => 'request_close',
            'close_requested_by' => Auth::id(),
            'close_requested_at' => Carbon::now(),
            'close_reason' => $validated['reason'],
        ]);

        return back()->with('success', "Permintaan penutupan PO {$purchaseOrder->po_number} berhasil diajukan.");
    }

    /**
     * Tutup transaksi Pesanan Pembelian secara definitif (Close PO)
     */
    public function close(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (!in_array($purchaseOrder->status, ['approved', 'partially_received', 'request_close'])) {
            return back()->with('error', 'PO dengan status saat ini tidak dapat ditutup.');
        }

        $reason = $validated['reason'] ?: $purchaseOrder->close_reason ?: 'Ditutup oleh manajemen / gudang.';

        $purchaseOrder->update([
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => Carbon::now(),
            'close_reason' => $reason,
        ]);

        return back()->with('success', "Pesanan Pembelian {$purchaseOrder->po_number} telah resmi ditutup.");
    }

    /**
     * Hapus draf Pesanan Pembelian
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->with('error', 'Hanya Pesanan Pembelian berstatus Draf yang dapat dihapus.');
        }

        $poNumber = $purchaseOrder->po_number;
        $purchaseOrder->delete();

        return redirect()->route('pembelian.po.index')
            ->with('success', "Pesanan Pembelian {$poNumber} berhasil dihapus.");
    }

    /**
     * Cetak atau unduh pratinjau PDF resmi dokumen PO
     */
    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing([
            'supplier',
            'cabang',
            'warehouse',
            'approvedBy',
            'createdBy',
            'purchaseOrderItem.product.uom',
            'purchaseOrderItem.currency',
            'purchaseOrderCurrency.currency',
        ]);

        return HelperController::cetakPO($purchaseOrder);
    }

    /**
     * Endpoint JSON: Detail Supplier (kontak, tempo hutang, info rekening)
     */
    public function supplierDetails(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'id' => $supplier->id,
            'code' => $supplier->code,
            'perusahaan' => $supplier->perusahaan,
            'kontak_person' => $supplier->kontak_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'tempo_hutang' => (int) ($supplier->tempo_hutang ?? 0),
            'nama_bank' => $supplier->nama_bank,
            'nomor_rekening' => $supplier->nomor_rekening,
            'nama_rekening' => $supplier->nama_rekening,
        ]);
    }

    /**
     * Endpoint JSON: Detail Produk (nama, SKU, UOM, harga beli acuan, default pajak)
     */
    public function productDetails(Product $product): JsonResponse
    {
        $product->loadMissing(['uom']);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'uom' => $product->uom?->name ?? 'Pcs',
            'cost_price' => (float) ($product->cost_price ?? 0),
            'tipe_pajak' => TaxTypeHelper::normalize($product->tipe_pajak ?? 'eklusif'),
            'pajak' => (float) ($product->pajak ?? 11),
        ]);
    }
}
