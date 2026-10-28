<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gudang\StorePurchaseReceiptRequest;
use App\Http\Requests\Gudang\UpdatePurchaseReceiptRequest;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptBiaya;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReceiptPhoto;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use App\Support\OrderRequestQuantityLock;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PenerimaanBarangController extends Controller
{
    protected PurchaseReceiptService $receiptService;

    public function __construct(PurchaseReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /**
     * Menampilkan daftar penerimaan barang (Goods Receipt / GRN).
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $search = $request->query('q');
        $warehouseId = $request->query('warehouse_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $baseQuery = PurchaseReceipt::with([
            'purchaseOrder.supplier',
            'receivedBy',
            'purchaseReceiptItem.product',
            'purchaseReceiptItem.warehouse',
        ])->latest('receipt_date')->latest('id');

        // Counters untuk KPI
        $kpiQuery = PurchaseReceipt::query();
        $counts = [
            'total' => (clone $kpiQuery)->count(),
            'draft' => (clone $kpiQuery)->where('status', 'draft')->count(),
            'partial' => (clone $kpiQuery)->where('status', 'partial')->count(),
            'completed' => (clone $kpiQuery)->where('status', 'completed')->count(),
            'this_month' => (clone $kpiQuery)->whereMonth('receipt_date', now()->month)
                                            ->whereYear('receipt_date', now()->year)
                                            ->count(),
        ];

        $query = clone $baseQuery;

        if ($status !== 'all' && in_array($status, ['draft', 'partial', 'completed'])) {
            $query->where('status', $status);
        }

        if ($warehouseId) {
            $query->whereHas('purchaseReceiptItem', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        if ($startDate) {
            $query->whereDate('receipt_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('receipt_date', '<=', $endDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('purchaseOrder', function ($poQuery) use ($search) {
                      $poQuery->where('po_number', 'like', "%{$search}%")
                              ->orWhereHas('supplier', function ($supQuery) use ($search) {
                                  $supQuery->where('name', 'like', "%{$search}%");
                              });
                  });
            });
        }

        $receipts = $query->paginate(15)->withQueryString();
        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        return view('gudang.penerimaan.index', compact(
            'receipts',
            'counts',
            'status',
            'search',
            'warehouseId',
            'startDate',
            'endDate',
            'warehouses'
        ));
    }

    /**
     * Menampilkan form pembuatan penerimaan barang baru.
     */
    public function create(Request $request): View
    {
        $selectedPoId = $request->query('purchase_order_id') ?? $request->query('po_id');
        $user = Auth::user();

        // PO yang siap diterima: status approved atau partially_received
        $purchaseOrders = PurchaseOrder::with(['supplier', 'warehouse', 'currency'])
            ->whereIn('status', ['approved', 'partially_received'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();
        $users = User::orderBy('name')->limit(100)->get();
        $currencies = Currency::orderBy('name')->get();

        $selectedPo = null;
        $poItemsData = [];

        if ($selectedPoId) {
            $selectedPo = PurchaseOrder::with([
                'supplier',
                'warehouse',
                'currency',
                'purchaseOrderItem.product.unit',
            ])->find($selectedPoId);

            if ($selectedPo) {
                foreach ($selectedPo->purchaseOrderItem as $item) {
                    $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $item->id);
                    if ($limit['remaining_received'] > 0) {
                        $poItemsData[] = [
                            'purchase_order_item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name ?? 'Produk #' . $item->product_id,
                            'product_sku' => $item->product->sku ?? '-',
                            'unit_name' => $item->product->unit->name ?? 'Pcs',
                            'ordered_qty' => (float) $item->quantity,
                            'received_qty' => (float) $limit['received_quantity'],
                            'accepted_qty' => (float) $limit['accepted_quantity'],
                            'remaining_received' => (float) $limit['remaining_received'],
                            'remaining_accepted' => (float) $limit['remaining_accepted'],
                            'qty_received' => (float) $limit['remaining_received'], // default terima semua sisa
                            'qty_accepted' => (float) $limit['remaining_accepted'],
                            'qty_rejected' => 0,
                            'reason_rejected' => '',
                            'warehouse_id' => $selectedPo->warehouse_id ?? $warehouses->first()?->id,
                            'rak_id' => null,
                        ];
                    }
                }
            }
        }

        return view('gudang.penerimaan.create', compact(
            'purchaseOrders',
            'warehouses',
            'users',
            'currencies',
            'selectedPo',
            'selectedPoId',
            'poItemsData'
        ));
    }

    /**
     * Menyimpan penerimaan barang baru ke basis data.
     */
    public function store(StorePurchaseReceiptRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $action = $request->input('action', 'save_draft');

        try {
            return DB::transaction(function () use ($request, $validated, $action) {
                $purchaseOrder = PurchaseOrder::findOrFail($validated['purchase_order_id']);

                // Generate Nomor Penerimaan Resmi (GRN-YYYYMMDD-XXXX)
                $receiptNumber = $this->receiptService->generateReceiptNumber();

                // Format catatan dengan nomor Surat Jalan Vendor jika ada
                $fullNotes = '';
                if (!empty($validated['supplier_delivery_note_number'])) {
                    $fullNotes = "[SJ Vendor: " . trim($validated['supplier_delivery_note_number']) . "]";
                }
                if (!empty($validated['notes'])) {
                    $fullNotes = $fullNotes ? $fullNotes . "\n" . trim($validated['notes']) : trim($validated['notes']);
                }

                $isDirectConfirm = ($action === 'save_and_confirm');
                $receiptStatus = $isDirectConfirm ? 'completed' : 'draft';

                $currencyId = $validated['currency_id']
                    ?? $purchaseOrder->purchaseOrderCurrency->first()?->currency_id
                    ?? $purchaseOrder->purchaseOrderItem->first()?->currency_id
                    ?? Currency::where('code', 'IDR')->value('id')
                    ?? 1;

                $purchaseReceipt = PurchaseReceipt::create([
                    'receipt_number' => $receiptNumber,
                    'purchase_order_id' => $purchaseOrder->id,
                    'receipt_date' => $validated['receipt_date'],
                    'received_by' => $validated['received_by'] ?? Auth::id() ?? 1,
                    'notes' => $fullNotes ?: null,
                    'currency_id' => $currencyId,
                    'other_cost' => 0,
                    'status' => $receiptStatus,
                    'cabang_id' => $validated['cabang_id'] ?? $purchaseOrder->cabang_id ?? Auth::user()->cabang_id ?? 1,
                ]);

                // Simpan item-item penerimaan
                foreach ($validated['items'] as $itemData) {
                    $qtyReceived = (float) $itemData['qty_received'];
                    $qtyAccepted = isset($itemData['qty_accepted']) ? (float) $itemData['qty_accepted'] : $qtyReceived;
                    $qtyRejected = isset($itemData['qty_rejected']) ? (float) $itemData['qty_rejected'] : 0;

                    PurchaseReceiptItem::create([
                        'purchase_receipt_id' => $purchaseReceipt->id,
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'],
                        'product_id' => $itemData['product_id'],
                        'qty_received' => $qtyReceived,
                        'qty_accepted' => $qtyAccepted,
                        'qty_rejected' => $qtyRejected,
                        'reason_rejected' => $qtyRejected > 0 ? ($itemData['reason_rejected'] ?? null) : null,
                        'warehouse_id' => $itemData['warehouse_id'],
                        'rak_id' => $itemData['rak_id'] ?? null,
                        'status' => $isDirectConfirm ? 'completed' : 'pending',
                    ]);
                }

                // Simpan lampiran foto surat jalan/barang fisik
                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $photoFile) {
                        if ($photoFile && $photoFile->isValid()) {
                            $path = $photoFile->store('purchase_receipt_photos', 'public');
                            PurchaseReceiptPhoto::create([
                                'purchase_receipt_id' => $purchaseReceipt->id,
                                'photo_url' => $path,
                            ]);
                        }
                    }
                }

                // Simpan biaya tambahan penerimaan jika ada
                if (!empty($validated['biayas'])) {
                    foreach ($validated['biayas'] as $biaya) {
                        PurchaseReceiptBiaya::create([
                            'purchase_receipt_id' => $purchaseReceipt->id,
                            'nama_biaya' => $biaya['nama_biaya'],
                            'total' => (float) $biaya['total'],
                            'coa_id' => $biaya['coa_id'] ?? null,
                            'currency_id' => $purchaseReceipt->currency_id,
                            'untuk_pembelian' => 0,
                            'masuk_invoice' => 0,
                        ]);
                    }
                }

                // Jika langsung konfirmasi & masuk stok
                if ($isDirectConfirm) {
                    $this->receiptService->postPurchaseReceipt($purchaseReceipt);
                    $this->handleReceivingQcAndAutoReturn($purchaseReceipt);
                    $purchaseOrder->syncReceiptFulfillmentStatus(Auth::id());
                }

                $message = $isDirectConfirm
                    ? "Penerimaan barang {$receiptNumber} berhasil disimpan dan stok gudang telah resmi diperbarui."
                    : "Draf penerimaan barang {$receiptNumber} berhasil disimpan.";

                return redirect()->route('gudang.penerimaan.show', $purchaseReceipt)
                    ->with('success', $message);
            });
        } catch (\Throwable $e) {
            Log::error('PenerimaanBarangController@store failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'Gagal menyimpan penerimaan barang: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail lengkap penerimaan barang (show view).
     */
    public function show(PurchaseReceipt $penerimaan): View
    {
        $penerimaan->load([
            'purchaseOrder.supplier',
            'purchaseOrder.warehouse',
            'purchaseOrder.currency',
            'receivedBy',
            'cabang',
            'purchaseReceiptItem.product.unit',
            'purchaseReceiptItem.warehouse',
            'purchaseReceiptItem.rak',
            'purchaseReceiptItem.purchaseOrderItem',
            'purchaseReceiptPhoto',
            'purchaseReceiptBiaya.coa',
            'journalEntries.coa',
        ]);

        return view('gudang.penerimaan.show', [
            'receipt' => $penerimaan,
        ]);
    }

    /**
     * Menampilkan formulir edit penerimaan barang (khusus status draft).
     */
    public function edit(PurchaseReceipt $penerimaan): View|RedirectResponse
    {
        if ($penerimaan->status !== 'draft') {
            return redirect()->route('gudang.penerimaan.show', $penerimaan)
                ->with('error', "Penerimaan barang dengan status '{$penerimaan->status}' tidak dapat diedit karena sudah diverifikasi/masuk stok.");
        }

        $penerimaan->load([
            'purchaseOrder.supplier',
            'purchaseOrder.warehouse',
            'purchaseReceiptItem.product.unit',
            'purchaseReceiptItem.warehouse',
            'purchaseReceiptItem.rak',
            'purchaseReceiptItem.purchaseOrderItem',
            'purchaseReceiptPhoto',
            'purchaseReceiptBiaya',
        ]);

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();
        $users = User::orderBy('name')->limit(100)->get();

        $itemsData = $penerimaan->purchaseReceiptItem->map(function ($item) {
            $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $item->purchase_order_item_id, (int) $item->id);
            return [
                'id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Produk #' . $item->product_id,
                'product_sku' => $item->product->sku ?? '-',
                'unit_name' => $item->product->unit->name ?? 'Pcs',
                'ordered_qty' => (float) ($item->purchaseOrderItem->quantity ?? 0),
                'remaining_received' => (float) $limit['remaining_received'],
                'qty_received' => (float) $item->qty_received,
                'qty_accepted' => (float) $item->qty_accepted,
                'qty_rejected' => (float) $item->qty_rejected,
                'reason_rejected' => $item->reason_rejected ?? '',
                'warehouse_id' => $item->warehouse_id,
                'rak_id' => $item->rak_id,
            ];
        });

        return view('gudang.penerimaan.edit', [
            'receipt' => $penerimaan,
            'warehouses' => $warehouses,
            'users' => $users,
            'itemsData' => $itemsData,
        ]);
    }

    /**
     * Memperbarui penerimaan barang (khusus status draft).
     */
    public function update(UpdatePurchaseReceiptRequest $request, PurchaseReceipt $penerimaan): RedirectResponse
    {
        if ($penerimaan->status !== 'draft') {
            return redirect()->route('gudang.penerimaan.show', $penerimaan)
                ->with('error', "Penerimaan barang dengan status '{$penerimaan->status}' tidak dapat diperbarui.");
        }

        $validated = $request->validated();
        $action = $request->input('action', 'save_draft');

        try {
            return DB::transaction(function () use ($request, $validated, $action, $penerimaan) {
                // Format catatan dengan nomor Surat Jalan Vendor jika ada
                $fullNotes = '';
                if (!empty($validated['supplier_delivery_note_number'])) {
                    $fullNotes = "[SJ Vendor: " . trim($validated['supplier_delivery_note_number']) . "]";
                }
                if (!empty($validated['notes'])) {
                    $fullNotes = $fullNotes ? $fullNotes . "\n" . trim($validated['notes']) : trim($validated['notes']);
                }

                $isDirectConfirm = ($action === 'save_and_confirm');
                $receiptStatus = $isDirectConfirm ? 'completed' : 'draft';

                $penerimaan->update([
                    'receipt_date' => $validated['receipt_date'],
                    'received_by' => $validated['received_by'] ?? $penerimaan->received_by,
                    'notes' => $fullNotes ?: null,
                    'status' => $receiptStatus,
                ]);

                // Update atau re-create item penerimaan
                // Hapus item lama yang tidak ada di request
                $submittedItemIds = collect($validated['items'])->pluck('id')->filter()->all();
                $penerimaan->purchaseReceiptItem()->whereNotIn('id', $submittedItemIds)->delete();

                foreach ($validated['items'] as $itemData) {
                    $qtyReceived = (float) $itemData['qty_received'];
                    $qtyAccepted = isset($itemData['qty_accepted']) ? (float) $itemData['qty_accepted'] : $qtyReceived;
                    $qtyRejected = isset($itemData['qty_rejected']) ? (float) $itemData['qty_rejected'] : 0;

                    $itemPayload = [
                        'purchase_receipt_id' => $penerimaan->id,
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'],
                        'product_id' => $itemData['product_id'],
                        'qty_received' => $qtyReceived,
                        'qty_accepted' => $qtyAccepted,
                        'qty_rejected' => $qtyRejected,
                        'reason_rejected' => $qtyRejected > 0 ? ($itemData['reason_rejected'] ?? null) : null,
                        'warehouse_id' => $itemData['warehouse_id'],
                        'rak_id' => $itemData['rak_id'] ?? null,
                        'status' => $isDirectConfirm ? 'completed' : 'pending',
                    ];

                    if (!empty($itemData['id'])) {
                        PurchaseReceiptItem::where('id', $itemData['id'])->update($itemPayload);
                    } else {
                        PurchaseReceiptItem::create($itemPayload);
                    }
                }

                // Tambahkan lampiran foto baru jika diunggah
                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $photoFile) {
                        if ($photoFile && $photoFile->isValid()) {
                            $path = $photoFile->store('purchase_receipt_photos', 'public');
                            PurchaseReceiptPhoto::create([
                                'purchase_receipt_id' => $penerimaan->id,
                                'photo_url' => $path,
                            ]);
                        }
                    }
                }

                // Jika langsung konfirmasi & masuk stok
                if ($isDirectConfirm) {
                    $this->receiptService->postPurchaseReceipt($penerimaan);
                    $this->handleReceivingQcAndAutoReturn($penerimaan);
                    if ($penerimaan->purchaseOrder) {
                        $penerimaan->purchaseOrder->syncReceiptFulfillmentStatus(Auth::id());
                    }
                }

                $message = $isDirectConfirm
                    ? "Penerimaan barang {$penerimaan->receipt_number} berhasil dikonfirmasi dan stok gudang telah resmi diperbarui."
                    : "Perubahan draf penerimaan barang {$penerimaan->receipt_number} berhasil disimpan.";

                return redirect()->route('gudang.penerimaan.show', $penerimaan)
                    ->with('success', $message);
            });
        } catch (\Throwable $e) {
            Log::error('PenerimaanBarangController@update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui penerimaan barang: ' . $e->getMessage());
        }
    }

    /**
     * Konfirmasi penerimaan draf menjadi resmi masuk stok gudang.
     */
    public function confirm(PurchaseReceipt $penerimaan): RedirectResponse
    {
        if ($penerimaan->status === 'completed') {
            return redirect()->route('gudang.penerimaan.show', $penerimaan)
                ->with('info', "Penerimaan barang ini sudah berstatus selesai/terkonfirmasi.");
        }

        try {
            DB::transaction(function () use ($penerimaan) {
                // Update semua item penerimaan menjadi completed
                $penerimaan->purchaseReceiptItem()->update(['status' => 'completed']);
                $penerimaan->update(['status' => 'completed']);

                // Posting persediaan dan jurnal via service
                $this->receiptService->postPurchaseReceipt($penerimaan);
                $this->handleReceivingQcAndAutoReturn($penerimaan);

                // Sinkronisasi status pemenuhan PO
                if ($penerimaan->purchaseOrder) {
                    $penerimaan->purchaseOrder->syncReceiptFulfillmentStatus(Auth::id());
                }
            });

            return redirect()->route('gudang.penerimaan.show', $penerimaan)
                ->with('success', "Penerimaan barang {$penerimaan->receipt_number} berhasil dikonfirmasi. Stok masuk gudang dan jurnal persediaan telah dibukukan.");
        } catch (\Throwable $e) {
            Log::error('PenerimaanBarangController@confirm failed: ' . $e->getMessage());

            return back()->with('error', 'Gagal mengonfirmasi penerimaan barang: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus draf penerimaan barang.
     */
    public function destroy(PurchaseReceipt $penerimaan): RedirectResponse
    {
        if ($penerimaan->status !== 'draft') {
            return back()->with('error', "Penerimaan barang dengan status '{$penerimaan->status}' tidak dapat dihapus karena sudah mempengaruhi stok fisik.");
        }

        try {
            DB::transaction(function () use ($penerimaan) {
                $po = $penerimaan->purchaseOrder;
                $penerimaan->delete();

                if ($po && $po->exists) {
                    $po->syncReceiptFulfillmentStatus(Auth::id());
                }
            });

            return redirect()->route('gudang.penerimaan.index')
                ->with('success', "Draf penerimaan barang {$penerimaan->receipt_number} berhasil dihapus.");
        } catch (\Throwable $e) {
            Log::error('PenerimaanBarangController@destroy failed: ' . $e->getMessage());

            return back()->with('error', 'Gagal menghapus penerimaan barang: ' . $e->getMessage());
        }
    }

    /**
     * Menghasilkan dokumen PDF Surat Jalan Masuk / Bukti Penerimaan Barang (GRN).
     */
    public function pdf(PurchaseReceipt $penerimaan): Response
    {
        $penerimaan->load([
            'purchaseOrder.supplier',
            'purchaseOrder.warehouse',
            'receivedBy',
            'cabang',
            'purchaseReceiptItem.product.unit',
            'purchaseReceiptItem.warehouse',
            'purchaseReceiptItem.rak',
        ]);

        $pdf = Pdf::loadView('pdf.purchase-receipt', [
            'receipt' => $penerimaan,
            'cabang' => $penerimaan->cabang ?? Cabang::first(),
        ])->setPaper('a4', 'portrait');

        $filename = 'Bukti-Penerimaan-' . ($penerimaan->receipt_number ?: 'GRN-' . $penerimaan->id) . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Mengambil item-item PO yang belum diterima via JSON AJAX.
     */
    public function poItems(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load([
            'supplier',
            'warehouse',
            'currency',
            'purchaseOrderItem.product.unit',
        ]);

        $items = [];
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $item->id);

            $items[] = [
                'purchase_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Produk #' . $item->product_id,
                'product_sku' => $item->product->sku ?? '-',
                'unit_name' => $item->product->unit->name ?? 'Pcs',
                'ordered_qty' => (float) $item->quantity,
                'received_qty' => (float) $limit['received_quantity'],
                'accepted_qty' => (float) $limit['accepted_quantity'],
                'remaining_received' => (float) $limit['remaining_received'],
                'remaining_accepted' => (float) $limit['remaining_accepted'],
                'default_qty' => (float) $limit['remaining_received'],
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'warehouse_name' => $purchaseOrder->warehouse->name ?? 'Gudang Utama',
            ];
        }

        return response()->json([
            'success' => true,
            'purchase_order' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier_name' => $purchaseOrder->supplier->name ?? '-',
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'warehouse_name' => $purchaseOrder->warehouse->name ?? '-',
                'currency_id' => $purchaseOrder->currency_id,
                'currency_code' => $purchaseOrder->currency->code ?? 'IDR',
                'cabang_id' => $purchaseOrder->cabang_id,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Otomatis membuat QualityControl dan PurchaseReturn draft jika terdapat barang yang ditolak (rejected).
     * Menerapkan aturan idempotensi anti-duplikasi retur (Bug C2 Guard).
     */
    protected function handleReceivingQcAndAutoReturn(PurchaseReceipt $purchaseReceipt): void
    {
        $purchaseReceipt->loadMissing([
            'purchaseReceiptItem.purchaseOrderItem',
            'purchaseReceiptItem.product',
            'purchaseOrder',
        ]);

        $rejectedItems = $purchaseReceipt->purchaseReceiptItem->filter(function ($item) {
            return (float) ($item->qty_rejected ?? 0) > 0;
        });

        if ($rejectedItems->isEmpty()) {
            return;
        }

        $purchaseOrder = $purchaseReceipt->purchaseOrder;
        $qcService = app(QualityControlService::class);
        $purchaseReturnService = app(PurchaseReturnService::class);

        // 1. Cari atau buat 1 dokumen QualityControl untuk mencatat hasil inspeksi
        $qualityControl = QualityControl::where('from_model_type', PurchaseReceipt::class)
            ->where('from_model_id', $purchaseReceipt->id)
            ->first();

        $totalReceived = (float) $purchaseReceipt->purchaseReceiptItem->sum('qty_received');
        $totalPassed = (float) $purchaseReceipt->purchaseReceiptItem->sum('qty_accepted');
        $totalRejected = (float) $rejectedItems->sum('qty_rejected');
        $firstItem = $purchaseReceipt->purchaseReceiptItem->first();
        $reasons = $rejectedItems->pluck('reason_rejected')->filter()->unique()->implode('; ');

        if (! $qualityControl) {
            $qualityControl = QualityControl::create([
                'qc_number'         => $qcService->generateQcNumber(),
                'purchase_order_id' => $purchaseOrder?->id,
                'inspected_by'      => Auth::id() ?? $purchaseReceipt->received_by ?? 1,
                'passed_quantity'   => $totalPassed,
                'rejected_quantity' => $totalRejected,
                'quantity_received' => $totalReceived,
                'notes'             => "QC Penerimaan Barang #{$purchaseReceipt->receipt_number}",
                'status'            => 1, // Selesai / Terproses
                'warehouse_id'      => $firstItem?->warehouse_id ?? $purchaseOrder?->warehouse_id,
                'reason_reject'     => $reasons ?: 'Barang rusak / ditolak saat penerimaan logistik',
                'product_id'        => $firstItem?->product_id,
                'from_model_type'   => PurchaseReceipt::class,
                'from_model_id'     => $purchaseReceipt->id,
                'purchase_return_processed' => now(),
                'cabang_id'         => $purchaseReceipt->cabang_id ?? $purchaseOrder?->cabang_id ?? 1,
            ]);
        } else {
            $qualityControl->update([
                'passed_quantity'   => $totalPassed,
                'rejected_quantity' => $totalRejected,
                'quantity_received' => $totalReceived,
                'reason_reject'     => $reasons ?: $qualityControl->reason_reject,
                'status'            => 1,
                'purchase_return_processed' => now(),
            ]);
        }

        // 2. Cari atau buat TEPAT 1 dokumen PurchaseReturn (Anti-Duplicate Return Guard Bug C2)
        $purchaseReturn = PurchaseReturn::where('purchase_receipt_id', $purchaseReceipt->id)
            ->orWhere('quality_control_id', $qualityControl->id)
            ->first();

        if (! $purchaseReturn) {
            $purchaseReturn = PurchaseReturn::create([
                'quality_control_id'  => $qualityControl->id,
                'purchase_receipt_id' => $purchaseReceipt->id,
                'failed_qc_action'    => PurchaseReturn::QC_ACTION_RETURN_SUPPLIER,
                'nota_retur'          => $purchaseReturnService->generateNotaRetur(),
                'return_date'         => now(),
                'created_by'          => Auth::id() ?? $purchaseReceipt->received_by ?? 1,
                'status'              => 'draft',
                'cabang_id'           => $purchaseReceipt->cabang_id ?? $purchaseOrder?->cabang_id ?? 1,
                'notes'               => "Retur otomatis dari QC penerimaan #{$purchaseReceipt->receipt_number} untuk PO #{$purchaseOrder?->po_number}.",
            ]);
        } else {
            if (!$purchaseReturn->quality_control_id) {
                $purchaseReturn->update(['quality_control_id' => $qualityControl->id]);
            }
            if (!$purchaseReturn->purchase_receipt_id) {
                $purchaseReturn->update(['purchase_receipt_id' => $purchaseReceipt->id]);
            }
        }

        // 3. Tambahkan item retur jika belum ada (idempotensi per item)
        foreach ($rejectedItems as $rejItem) {
            $hasItem = PurchaseReturnItem::where('purchase_return_id', $purchaseReturn->id)
                ->where(function ($q) use ($rejItem) {
                    $q->where('purchase_receipt_item_id', $rejItem->id)
                      ->orWhere('product_id', $rejItem->product_id);
                })
                ->exists();

            if (! $hasItem) {
                $poItem = $rejItem->purchaseOrderItem;
                PurchaseReturnItem::create([
                    'purchase_return_id'       => $purchaseReturn->id,
                    'purchase_receipt_item_id' => $rejItem->id,
                    'product_id'               => $rejItem->product_id,
                    'qty_returned'             => (float) $rejItem->qty_rejected,
                    'unit_price'               => $poItem?->unit_price ?? $rejItem->product?->cost_price ?? 0,
                    'reason'                   => $rejItem->reason_rejected ?? 'Ditolak saat pemeriksaan penerimaan barang',
                ]);
            }
        }
    }
}
