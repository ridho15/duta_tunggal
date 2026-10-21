<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\StorePurchaseInvoiceRequest;
use App\Http\Requests\Pembelian\UpdatePurchaseInvoiceRequest;
use App\Models\Cabang;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Services\InvoiceService;
use App\Services\PurchaseInvoiceAccountingService;
use App\Services\PurchaseInvoiceCancellationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PurchaseInvoiceController extends Controller
{
    protected PurchaseInvoiceAccountingService $accountingService;
    protected PurchaseInvoiceCancellationService $cancellationService;
    protected InvoiceService $invoiceService;

    public function __construct(
        PurchaseInvoiceAccountingService $accountingService,
        PurchaseInvoiceCancellationService $cancellationService,
        InvoiceService $invoiceService
    ) {
        $this->accountingService = $accountingService;
        $this->cancellationService = $cancellationService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Tampilkan daftar Faktur Pembelian (Purchase Invoices).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('search', $request->query('q', '')));
        $supplierFilter = $request->query('supplier_id');
        $cabangFilter = $request->query('cabang_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Query dasar faktur pembelian
        $baseQuery = Invoice::with(['supplier', 'cabang', 'accountPayable'])
            ->where(function ($q) {
                $q->where('from_model_type', PurchaseOrder::class)
                  ->orWhere('from_model_type', 'App\Models\PurchaseOrder')
                  ->orWhereNotNull('purchase_receipts');
            });

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        }

        // Hitung live counter per status untuk tab
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', Invoice::STATUS_DRAFT)->count(),
            'unpaid' => (clone $baseQuery)->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID])->count(),
            'overdue' => (clone $baseQuery)
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('due_date', '<', today())
                ->count(),
            'paid' => (clone $baseQuery)->where('status', Invoice::STATUS_PAID)->count(),
            'cancelled' => (clone $baseQuery)->where('status', Invoice::STATUS_CANCELLED)->count(),
        ];

        // 4 KPI Cards
        $kpi = [
            'total_amount' => (float) (clone $baseQuery)->where('status', '!=', Invoice::STATUS_CANCELLED)->sum('total'),
            'unpaid_amount' => (float) (clone $baseQuery)->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID])->sum('total'),
            'overdue_count' => $counts['overdue'],
            'paid_count' => $counts['paid'],
        ];

        $query = clone $baseQuery;

        // Filter status berdasarkan tab
        match ($activeTab) {
            'draft' => $query->where('status', Invoice::STATUS_DRAFT),
            'unpaid' => $query->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID]),
            'overdue' => $query->where('status', '!=', Invoice::STATUS_PAID)
                               ->where('status', '!=', Invoice::STATUS_CANCELLED)
                               ->where('due_date', '<', today()),
            'paid' => $query->where('status', Invoice::STATUS_PAID),
            'cancelled' => $query->where('status', Invoice::STATUS_CANCELLED),
            default => $query,
        };

        // Filter Cabang
        if (!empty($cabangFilter) && $canManageAll) {
            $query->where('cabang_id', $cabangFilter);
        }

        // Filter Supplier
        if (!empty($supplierFilter)) {
            $query->where('supplier_id', $supplierFilter);
        }

        // Filter Rentang Tanggal
        if (!empty($dateFrom)) {
            $query->whereDate('invoice_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('invoice_date', '<=', $dateTo);
        }

        // Pencarian multi-kolom
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                  ->orWhere('tax_invoice_number', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('perusahaan', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        $invoices = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('perusahaan')->get();
        $cabangs = Cabang::where('status', true)->get();

        return view('pembelian.invoice.index', compact(
            'invoices',
            'counts',
            'kpi',
            'suppliers',
            'cabangs',
            'activeTab',
            'search',
            'supplierFilter',
            'cabangFilter',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Tampilkan form pembuatan Faktur Pembelian baru.
     */
    public function create(Request $request): View
    {
        $supplierId = $request->query('supplier_id');
        $receiptId = $request->query('receipt_id');

        if ($receiptId && !$supplierId) {
            $receipt = PurchaseReceipt::with('purchaseOrder')->find($receiptId);
            if ($receipt && $receipt->purchaseOrder) {
                $supplierId = $receipt->purchaseOrder->supplier_id;
            }
        }

        $suppliers = Supplier::orderBy('perusahaan')->get();
        $cabangs = Cabang::where('status', true)->get();
        $activeCabang = Auth::user()->cabang ?? Cabang::first();

        return view('pembelian.invoice.create', compact(
            'suppliers',
            'cabangs',
            'activeCabang',
            'supplierId',
            'receiptId'
        ));
    }

    /**
     * Endpoint AJAX: Mengambil daftar Penerimaan Barang (GRN) yang belum difakturkan untuk supplier tertentu.
     */
    public function availableReceipts(Request $request, Supplier $supplier): JsonResponse
    {
        // Cari ID GRN yang sudah aktif tertagih di invoice non-batal
        $invoicedReceiptIds = Invoice::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereNotNull('purchase_receipts')
            ->pluck('purchase_receipts')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $receipts = PurchaseReceipt::with(['purchaseOrder', 'purchaseReceiptItem.product'])
            ->whereIn('status', ['partial', 'completed'])
            ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            })
            ->whereNotIn('id', $invoicedReceiptIds)
            ->orderBy('receipt_date', 'desc')
            ->get();

        $data = $receipts->map(function ($r) {
            return [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'receipt_date' => $r->receipt_date ? Carbon::parse($r->receipt_date)->format('d/m/Y') : '-',
                'status' => $r->status,
                'status_label' => ucfirst($r->status),
                'po_id' => $r->purchase_order_id,
                'po_number' => $r->purchaseOrder?->po_number ?? '-',
                'items_count' => $r->purchaseReceiptItem->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->kontak_person ?? $supplier->perusahaan,
                'perusahaan' => $supplier->perusahaan,
                'tempo_hutang' => $supplier->tempo_hutang ?? 30,
            ],
            'receipts' => $data,
        ]);
    }

    /**
     * Endpoint AJAX: Mengambil detail baris barang dan kalkulasi dari GRN terpilih.
     */
    public function receiptDetails(Request $request): JsonResponse
    {
        $receiptIds = (array) ($request->query('receipt_ids', $request->input('receipt_ids', [])));
        $receiptIds = collect($receiptIds)->map(fn ($id) => (int) $id)->filter()->values()->all();

        if (empty($receiptIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada Penerimaan Barang yang dipilih.',
                'data' => null,
            ], 422);
        }

        try {
            $expectedData = $this->accountingService->expectedReceiptBackedInvoiceDataFromReceiptIds($receiptIds);
            if (!$expectedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memuat item dari penerimaan barang terpilih.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $expectedData,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Simpan Faktur Pembelian baru (Draft atau Langsung Terbit/Posted).
     */
    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $action = $request->input('action', 'save_and_post');

        try {
            $invoice = DB::transaction(function () use ($validated, $action, $request) {
                // 1. Normalisasi form data dari GRN dan validasi 3-Way Matching
                if (isset($validated['items'])) {
                    $validated['invoiceItem'] = $validated['items'];
                }
                $normalized = $this->accountingService->normalizeFormData($validated);
                $resolved = $this->accountingService->validateReceiptBackedCreateData($normalized, lockForUpdate: true);
                $merged = array_merge($normalized, $resolved);

                $invoiceNumber = $this->invoiceService->generatePurchaseInvoiceNumber();

                // 2. Kalkulasi financial header
                $items = collect($merged['invoiceItem'] ?? []);
                $subtotal = (float) $items->sum('total');
                $otherFee = (float) ($validated['other_fee'] ?? 0);
                $priceVariance = (float) ($merged['price_variance_amount'] ?? 0);

                // Hitung total pajak PPN
                $taxTotal = 0.0;
                foreach ($items as $it) {
                    $taxTotal += (float) ($it['tax_amount'] ?? 0);
                }

                $totalAmount = round($subtotal + $taxTotal + $otherFee, 2);

                $status = ($action === 'save_draft') ? Invoice::STATUS_DRAFT : Invoice::STATUS_SENT;

                // 3. Simpan header invoice
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                    'tax_invoice_number' => $validated['tax_invoice_number'] ?? null,
                    'from_model_type' => PurchaseOrder::class,
                    'from_model_id' => $resolved['from_model_id'],
                    'purchase_order_ids' => $resolved['purchase_order_ids'],
                    'purchase_receipts' => $resolved['purchase_receipts'],
                    'supplier_id' => $resolved['supplier_id'],
                    'supplier_name' => $resolved['supplier_name'],
                    'supplier_phone' => $resolved['supplier_phone'],
                    'invoice_date' => $validated['invoice_date'],
                    'due_date' => $validated['due_date'],
                    'cabang_id' => $resolved['cabang_id'],
                    'subtotal' => $subtotal,
                    'dpp' => $subtotal,
                    'tax' => $items->first()['tax_rate'] ?? 0,
                    'ppn_rate' => $items->first()['tax_rate'] ?? 0,
                    'tipe_pajak' => $normalized['tipe_pajak'] ?? 'Include',
                    'other_fee' => $otherFee > 0 ? [['name' => 'Ongkir / Biaya Lain', 'amount' => $otherFee]] : [],
                    'price_variance_amount' => $priceVariance,
                    'total' => $totalAmount,
                    'status' => $status,
                ]);

                // 4. Simpan baris item
                foreach ($items as $itemData) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'price' => $itemData['price'],
                        'po_price' => $itemData['po_price'] ?? $itemData['price'],
                        'subtotal' => $itemData['subtotal'] ?? round($itemData['price'] * $itemData['quantity'], 2),
                        'tax_rate' => $itemData['tax_rate'] ?? 0,
                        'tax_amount' => $itemData['tax_amount'] ?? 0,
                        'total' => $itemData['total'] ?? round($itemData['price'] * $itemData['quantity'], 2),
                    ]);
                }

                // 5. Jika langsung terbitkan, posting ke Buku Besar & Hutang Dagang (AP)
                if ($action === 'save_and_post') {
                    $this->accountingService->finaliseInvoice($invoice);
                }

                return $invoice;
            });

            $msg = ($action === 'save_draft')
                ? "Faktur Pembelian {$invoice->invoice_number} berhasil disimpan sebagai Draft."
                : "Faktur Pembelian {$invoice->invoice_number} berhasil diterbitkan dan diposting ke Hutang Usaha (AP).";

            return redirect()->route('pembelian.invoices.show', $invoice)->with('success', $msg);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('PurchaseInvoiceController@store failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Gagal menyimpan Faktur Pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail Faktur Pembelian, 4-stage stepper, 3-Way Matching, dan Jurnal GL.
     */
    public function show(Invoice $invoice): View
    {
        $invoice->loadMissing([
            'supplier',
            'cabang',
            'invoiceItem.product.uom',
            'accountPayable',
            'journalEntries.coa',
            'cancelledBy',
        ]);

        // Muat data relasi 3-Way Matching
        $relatedPoIds = $invoice->purchase_order_ids ?? ($invoice->from_model_id ? [$invoice->from_model_id] : []);
        $relatedPos = PurchaseOrder::with('supplier')->whereIn('id', (array) $relatedPoIds)->get();

        $relatedReceiptIds = $invoice->purchase_receipts ?? [];
        $relatedReceipts = PurchaseReceipt::with(['purchaseOrder', 'purchaseReceiptItem.warehouse'])->whereIn('id', (array) $relatedReceiptIds)->get();

        return view('pembelian.invoice.show', compact('invoice', 'relatedPos', 'relatedReceipts'));
    }

    /**
     * Tampilkan form edit faktur pembelian (hanya untuk status draft).
     */
    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if (strtolower((string) $invoice->status) !== Invoice::STATUS_DRAFT) {
            return redirect()->route('pembelian.invoices.show', $invoice)
                ->with('error', 'Faktur yang telah diposting atau diproses tidak dapat diedit.');
        }

        $invoice->loadMissing(['supplier', 'cabang', 'invoiceItem.product.uom']);

        return view('pembelian.invoice.edit', compact('invoice'));
    }

    /**
     * Simpan perubahan pada faktur pembelian draft.
     */
    public function update(UpdatePurchaseInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        if (strtolower((string) $invoice->status) !== Invoice::STATUS_DRAFT) {
            return redirect()->route('pembelian.invoices.show', $invoice)
                ->with('error', 'Faktur yang telah diposting tidak dapat diubah.');
        }

        $validated = $request->validated();
        $action = $request->input('action', 'update_draft');

        try {
            DB::transaction(function () use ($invoice, $validated, $action, $request) {
                // Update header fields
                $invoice->update([
                    'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? $invoice->supplier_invoice_number,
                    'tax_invoice_number' => $validated['tax_invoice_number'] ?? $invoice->tax_invoice_number,
                    'invoice_date' => $validated['invoice_date'],
                    'due_date' => $validated['due_date'],
                ]);

                // Update item prices if submitted
                if (!empty($validated['items']) && is_array($validated['items'])) {
                    $totalVariance = 0.0;
                    $subtotal = 0.0;
                    $taxTotal = 0.0;

                    foreach ($validated['items'] as $itemData) {
                        $item = $invoice->invoiceItem()->where('product_id', $itemData['product_id'])->first();
                        if ($item) {
                            $price = (float) $itemData['price'];
                            $poPrice = (float) ($item->po_price ?? $price);
                            $qty = (float) $item->quantity;
                            $lineTotal = round($price * $qty, 2);
                            $lineTax = round($lineTotal * ((float)$item->tax_rate / 100), 2);
                            $lineVariance = round(($price - $poPrice) * $qty, 2);

                            $item->update([
                                'price' => $price,
                                'subtotal' => $lineTotal,
                                'total' => $lineTotal,
                                'tax_amount' => $lineTax,
                            ]);

                            $subtotal += $lineTotal;
                            $taxTotal += $lineTax;
                            $totalVariance += $lineVariance;
                        }
                    }

                    $otherFee = (float) ($validated['other_fee'] ?? $invoice->other_fee_total);
                    $total = round($subtotal + $taxTotal + $otherFee, 2);

                    $invoice->update([
                        'subtotal' => $subtotal,
                        'dpp' => $subtotal,
                        'total' => $total,
                        'price_variance_amount' => round($totalVariance, 2),
                    ]);
                }

                // Jika aksi adalah update & post, posting ke GL & AP
                if ($action === 'update_and_post') {
                    $this->accountingService->postAndApproveInvoice($invoice);
                }
            });

            $msg = ($action === 'update_and_post')
                ? "Faktur Pembelian {$invoice->invoice_number} berhasil diperbarui dan diposting ke Hutang Usaha."
                : "Faktur Pembelian {$invoice->invoice_number} berhasil diperbarui.";

            return redirect()->route('pembelian.invoices.show', $invoice)->with('success', $msg);
        } catch (Throwable $e) {
            Log::error('PurchaseInvoiceController@update failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui Faktur Pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Hapus faktur pembelian draft.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        if (strtolower((string) $invoice->status) !== Invoice::STATUS_DRAFT) {
            return back()->with('error', 'Hanya Faktur Pembelian berstatus Draft yang dapat dihapus.');
        }

        try {
            DB::transaction(function () use ($invoice) {
                $invoice->invoiceItem()->delete();
                $invoice->delete();
            });

            return redirect()->route('pembelian.invoices.index')
                ->with('success', "Faktur Pembelian {$invoice->invoice_number} berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('PurchaseInvoiceController@destroy failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus Faktur Pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Setujui & Posting faktur pembelian draft ke Buku Besar (GL) dan Hutang Dagang (AP).
     */
    public function post(Invoice $invoice): RedirectResponse
    {
        if (strtolower((string) $invoice->status) !== Invoice::STATUS_DRAFT) {
            return back()->with('error', 'Faktur ini sudah diposting sebelumnya.');
        }

        try {
            $this->accountingService->postAndApproveInvoice($invoice);

            return back()->with('success', "Faktur Pembelian {$invoice->invoice_number} berhasil disetujui dan diposting ke Buku Besar serta Hutang Usaha (AP).");
        } catch (Throwable $e) {
            Log::error('PurchaseInvoiceController@post failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal memposting Faktur Pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Batalkan Faktur Pembelian dengan alasan & pembalikkan jurnal akuntansi otomatis.
     */
    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        try {
            $this->cancellationService->cancel(
                $invoice,
                $request->cancel_reason,
                null,
                Auth::id()
            );

            return back()->with('success', "Faktur Pembelian {$invoice->invoice_number} berhasil dibatalkan dan entri jurnal akuntansi telah dibalikkan.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('PurchaseInvoiceController@cancel failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal membatalkan Faktur Pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Cetak atau unduh dokumen Faktur Pembelian resmi berskala A4 / PDF via DomPDF.
     */
    public function pdf(Invoice $invoice)
    {
        $invoice->loadMissing([
            'supplier',
            'cabang',
            'invoiceItem.product.uom',
            'accountPayable',
        ]);

        $pdf = Pdf::loadView('pembelian.invoice.pdf', compact('invoice'))
            ->setPaper('a4', 'portrait');

        $fileName = 'Faktur-Pembelian-' . str_replace('/', '-', $invoice->invoice_number) . '.pdf';

        return $pdf->stream($fileName);
    }
}
