<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Warehouse;
use App\Services\CreditNoteService;
use App\Services\CustomerReturnService;
use App\Services\DocumentPrintBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CustomerReturnController extends Controller
{
    protected CustomerReturnService $returnService;
    protected DocumentPrintBuilder $docBuilder;

    public function __construct(
        CustomerReturnService $returnService,
        DocumentPrintBuilder $docBuilder
    ) {
        $this->returnService = $returnService;
        $this->docBuilder = $docBuilder;
    }

    /**
     * Menampilkan daftar transaksi Retur Penjualan (Customer Return).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');
        $warehouseId = $request->query('warehouse_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Query dasar
        $baseQuery = CustomerReturn::query()
            ->with([
                'customer',
                'invoice',
                'cabang',
                'warehouse',
                'customerReturnItems.product',
                'customerReturnItems.invoiceItem',
            ]);

        // Pembatasan cabang
        $manageType = $user?->manage_type ?? [];
        if (!in_array('all', (array)$manageType) && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        } elseif (!empty($cabangId)) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        if (!empty($warehouseId)) {
            $baseQuery->where('warehouse_id', $warehouseId);
        }

        if (!empty($dateFrom)) {
            $baseQuery->whereDate('return_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $baseQuery->whereDate('return_date', '<=', $dateTo);
        }

        // Hitung counter status untuk tabs
        $counts = [
            'semua'         => (clone $baseQuery)->count(),
            'pending'       => (clone $baseQuery)->where('status', CustomerReturn::STATUS_PENDING)->count(),
            'received'      => (clone $baseQuery)->where('status', CustomerReturn::STATUS_RECEIVED)->count(),
            'qc_inspection' => (clone $baseQuery)->where('status', CustomerReturn::STATUS_QC_INSPECTION)->count(),
            'approved'      => (clone $baseQuery)->where('status', CustomerReturn::STATUS_APPROVED)->count(),
            'completed'     => (clone $baseQuery)->where('status', CustomerReturn::STATUS_COMPLETED)->count(),
            'rejected'      => (clone $baseQuery)->where('status', CustomerReturn::STATUS_REJECTED)->count(),
        ];

        // KPI Ringkasan
        $kpis = [
            'total_returns'      => $counts['semua'],
            'waiting_processing' => $counts['pending'] + $counts['received'] + $counts['qc_inspection'],
            'approved_ready'     => $counts['approved'],
            'completed_count'    => $counts['completed'],
        ];

        // Nilai total finansial retur yang telah diselesaikan
        $completedReturns = (clone $baseQuery)->where('status', CustomerReturn::STATUS_COMPLETED)->get();
        $totalCompletedFinancial = 0.0;
        foreach ($completedReturns as $cr) {
            foreach ($cr->customerReturnItems as $item) {
                if ($item->decision !== CustomerReturnItem::DECISION_REJECT && $item->invoiceItem) {
                    $invQty = max(0.0001, (float) $item->invoiceItem->quantity);
                    $subtotal = (float) $item->invoiceItem->subtotal;
                    $tax = (float) $item->invoiceItem->tax_amount;
                    $unitTotal = ($subtotal + $tax) / $invQty;
                    $totalCompletedFinancial += round((float)$item->quantity * $unitTotal, 2);
                }
            }
        }
        $kpis['total_financial'] = $totalCompletedFinancial;

        // Terapkan filter tab
        $query = clone $baseQuery;
        if ($tab !== 'semua' && array_key_exists($tab, CustomerReturn::STATUS_LABELS)) {
            $query->where('status', $tab);
        }

        // Pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', function ($iq) use ($search) {
                        $iq->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        $returns = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->orderBy('nama')->get();
        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        return view('penjualan.returns.index', compact(
            'returns',
            'counts',
            'kpis',
            'tab',
            'search',
            'cabangs',
            'warehouses',
            'cabangId',
            'warehouseId',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Menampilkan form pembuatan pengajuan Retur Penjualan baru.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $preselectedCustomerId = $request->query('customer_id');
        $preselectedInvoiceId = $request->query('invoice_id');

        $initialInvoice = null;
        if ($preselectedInvoiceId) {
            $initialInvoice = Invoice::withoutGlobalScopes()->find($preselectedInvoiceId);
            if ($initialInvoice && !$preselectedCustomerId) {
                $preselectedCustomerId = $initialInvoice->accountReceivable?->customer_id ?? $initialInvoice->customer?->id;
            }
        }

        // Pelanggan yang memiliki invoice aktif
        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        // Gudang penerima (sesuai cabang pengguna)
        $warehouseQuery = Warehouse::where('status', 1);
        $manageType = $user?->manage_type ?? [];
        if (!in_array('all', (array)$manageType) && $user?->cabang_id) {
            $warehouseQuery->where('cabang_id', $user->cabang_id);
        }
        $warehouses = $warehouseQuery->orderBy('name')->get();

        // Opsi keputusan item
        $decisionOptions = CustomerReturnItem::decisionOptions();

        return view('penjualan.returns.create', compact(
            'customers',
            'warehouses',
            'decisionOptions',
            'preselectedCustomerId',
            'preselectedInvoiceId'
        ));
    }

    /**
     * Endpoint JSON untuk mengambil invoice dan item-itemnya yang masih bisa diretur.
     */
    public function customerInvoices(Customer $customer): JsonResponse
    {
        $customerId = $customer->id;

        // Ambil invoice pelanggan yang valid (bukan draft / cancelled)
        $invoices = Invoice::query()
            ->where(function ($q) use ($customerId, $customer) {
                $q->whereHas('accountReceivable', fn ($ar) => $ar->where('customer_id', $customerId))
                    ->orWhere(function ($soQ) use ($customerId) {
                        $soQ->where('from_model_type', 'App\\Models\\SaleOrder')
                            ->whereIn('from_model_id', function ($sub) use ($customerId) {
                                $sub->select('id')->from('sale_orders')->where('customer_id', $customerId)->whereNull('deleted_at');
                            });
                    })
                    ->orWhere('customer_name', $customer->name);
            })
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->with(['invoiceItem.product.uom', 'cabang'])
            ->orderByDesc('invoice_date')
            ->get()
            ->map(function (Invoice $inv) {
                $items = [];
                foreach ($inv->invoiceItem as $item) {
                    // Hitung kuantitas yang sudah pernah diretur dan disetujui/selesai
                    $alreadyReturned = (float) CustomerReturnItem::where('invoice_item_id', $item->id)
                        ->whereHas('customerReturn', fn ($q) => $q->whereIn('status', [
                            CustomerReturn::STATUS_PENDING,
                            CustomerReturn::STATUS_RECEIVED,
                            CustomerReturn::STATUS_QC_INSPECTION,
                            CustomerReturn::STATUS_APPROVED,
                            CustomerReturn::STATUS_COMPLETED,
                        ]))
                        ->sum('quantity');

                    $maxReturnable = max(0, (float) $item->quantity - $alreadyReturned);

                    $items[] = [
                        'invoice_item_id' => $item->id,
                        'product_id'      => $item->product_id,
                        'product_name'    => $item->product?->name ?? 'Produk #' . $item->product_id,
                        'product_sku'     => $item->product?->sku ?? '-',
                        'uom'             => $item->product?->uom?->abbreviation ?? 'pcs',
                        'invoiced_qty'    => (float) $item->quantity,
                        'already_returned'=> $alreadyReturned,
                        'max_returnable'  => $maxReturnable,
                        'unit_price'      => (float) ($item->unit_price ?? $item->net_unit_price ?? 0),
                        'unit_price_formatted' => 'Rp ' . number_format((float) ($item->unit_price ?? $item->net_unit_price ?? 0), 0, ',', '.'),
                    ];
                }

                return [
                    'id'             => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date'   => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-',
                    'total'          => (float) $inv->total,
                    'total_formatted'=> 'Rp ' . number_format((float) $inv->total, 0, ',', '.'),
                    'cabang_id'      => $inv->cabang_id,
                    'cabang_name'    => $inv->cabang?->nama ?? '-',
                    'items'          => $items,
                ];
            })
            ->filter(fn ($inv) => collect($inv['items'])->sum('max_returnable') > 0)
            ->values()
            ->all();

        return response()->json([
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'phone' => $customer->phone ?? '-',
            ],
            'invoices' => $invoices,
        ]);
    }

    /**
     * Menyimpan transaksi Retur Penjualan baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'invoice_id'   => ['required', 'exists:invoices,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'return_date'  => ['required', 'date'],
            'reason'       => ['required', 'string', 'min:5', 'max:1000'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'items'        => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id'     => ['required', 'exists:invoice_items,id'],
            'items.*.product_id'          => ['required', 'exists:products,id'],
            'items.*.quantity'             => ['required', 'numeric', 'min:0.01'],
            'items.*.problem_description' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'invoice_id.required'  => 'Faktur Penjualan (Invoice) wajib dipilih.',
            'return_date.required' => 'Tanggal retur wajib diisi.',
            'reason.required'      => 'Alasan retur wajib diisi.',
            'reason.min'           => 'Alasan retur minimal 5 karakter.',
            'items.required'       => 'Pilih minimal satu produk yang ingin diretur.',
            'items.min'            => 'Pilih minimal satu produk yang ingin diretur.',
        ]);

        $invoice = Invoice::with('cabang')->findOrFail($validated['invoice_id']);
        $user = Auth::user();

        // Tentukan cabang (utamakan cabang dari invoice rujukan, fallback ke cabang user yang valid, atau cabang pertama)
        $userCabangValid = $user?->cabang_id && Cabang::where('id', $user->cabang_id)->exists();
        $cabangId = $invoice->cabang_id ?: ($userCabangValid ? $user->cabang_id : (Cabang::first()?->id ?? 27));

        try {
            $customerReturn = DB::transaction(function () use ($validated, $invoice, $cabangId) {
                // Generate nomor return
                $returnNumber = CustomerReturn::generateReturnNumber($cabangId);

                $return = CustomerReturn::create([
                    'return_number' => $returnNumber,
                    'invoice_id'    => $invoice->id,
                    'customer_id'   => $validated['customer_id'],
                    'cabang_id'     => $cabangId,
                    'warehouse_id'  => $validated['warehouse_id'] ?? null,
                    'return_date'   => $validated['return_date'],
                    'reason'        => $validated['reason'],
                    'status'        => CustomerReturn::STATUS_PENDING,
                    'notes'         => $validated['notes'] ?? null,
                ]);

                foreach ($validated['items'] as $itemData) {
                    $invItem = InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('id', $itemData['invoice_item_id'])
                        ->firstOrFail();

                    $qty = (float) $itemData['quantity'];
                    if ($qty <= 0) {
                        continue;
                    }

                    // Validasi sisa kuantitas yang bisa diretur
                    $alreadyReturned = (float) CustomerReturnItem::where('invoice_item_id', $invItem->id)
                        ->whereHas('customerReturn', fn ($q) => $q->whereIn('status', [
                            CustomerReturn::STATUS_PENDING,
                            CustomerReturn::STATUS_RECEIVED,
                            CustomerReturn::STATUS_QC_INSPECTION,
                            CustomerReturn::STATUS_APPROVED,
                            CustomerReturn::STATUS_COMPLETED,
                        ]))
                        ->sum('quantity');

                    $maxReturnable = max(0, (float) $invItem->quantity - $alreadyReturned);
                    if ($qty > $maxReturnable + 0.001) {
                        throw new \Exception("Kuantitas retur produk ({$qty} pcs) melebihi batas maksimal yang dapat diretur ({$maxReturnable} pcs).");
                    }

                    CustomerReturnItem::create([
                        'customer_return_id' => $return->id,
                        'product_id'         => $itemData['product_id'],
                        'invoice_item_id'    => $itemData['invoice_item_id'],
                        'quantity'           => $qty,
                        'problem_description'=> $itemData['problem_description'] ?? null,
                        'qc_result'          => null,
                        'qc_notes'           => null,
                        'decision'           => null,
                    ]);
                }

                return $return;
            });

            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('success', "Pengajuan Retur Penjualan #{$customerReturn->return_number} berhasil dibuat dengan status Menunggu (Pending).");
        } catch (Throwable $e) {
            Log::error('CustomerReturnController::store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request'   => $request->all(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat pengajuan retur: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail Retur Penjualan.
     */
    public function show(CustomerReturn $customerReturn): View
    {
        $customerReturn->loadMissing([
            'customer',
            'invoice.cabang',
            'cabang',
            'warehouse',
            'customerReturnItems.product.uom',
            'customerReturnItems.invoiceItem',
            'receivedBy',
            'qcInspectedBy',
            'approvedBy',
            'rejectedBy',
            'stockMovements.warehouse',
        ]);

        // Jurnal Akuntansi terkait (jika retur sudah selesai diproses)
        $journals = JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->with(['coa', 'cabang'])
            ->orderBy('id', 'asc')
            ->get();

        // Hitung estimasi nilai finansial & HPP
        $totalHppRestored = 0.0;
        $totalFinancial = 0.0;
        foreach ($customerReturn->customerReturnItems as $item) {
            $qty = (float) $item->quantity;
            $unitCost = (float) ($item->product?->cost_price ?? $item->invoiceItem?->cost_price ?? 0);
            $totalHppRestored += round($qty * $unitCost, 2);

            if ($item->invoiceItem) {
                $invQty = max(0.0001, (float) $item->invoiceItem->quantity);
                $unitTotal = ((float) $item->invoiceItem->subtotal + (float) $item->invoiceItem->tax_amount) / $invQty;
                $totalFinancial += round($qty * $unitTotal, 2);
            }
        }

        $decisionOptions = CustomerReturnItem::decisionOptions();
        $doc = $this->docBuilder->customerReturn($customerReturn);

        return view('penjualan.returns.show', compact(
            'customerReturn',
            'journals',
            'totalHppRestored',
            'totalFinancial',
            'decisionOptions',
            'doc'
        ));
    }

    /**
     * Transisi status ke Diterima (Received).
     */
    public function markReceived(CustomerReturn $customerReturn): RedirectResponse
    {
        if ($customerReturn->status !== CustomerReturn::STATUS_PENDING) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Aksi tidak valid untuk status transaksi saat ini.');
        }

        $customerReturn->update([
            'status'      => CustomerReturn::STATUS_RECEIVED,
            'received_by' => Auth::id(),
            'received_at' => now(),
        ]);

        return redirect()->route('penjualan.returns.show', $customerReturn->id)
            ->with('success', 'Barang retur telah berhasil ditandai sebagai Diterima oleh Gudang.');
    }

    /**
     * Transisi status ke Inspeksi QC (QC Inspection).
     */
    public function startQc(CustomerReturn $customerReturn): RedirectResponse
    {
        if ($customerReturn->status !== CustomerReturn::STATUS_RECEIVED) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Aksi tidak valid untuk status transaksi saat ini.');
        }

        $customerReturn->update([
            'status'          => CustomerReturn::STATUS_QC_INSPECTION,
            'qc_inspected_by' => Auth::id(),
            'qc_inspected_at' => now(),
        ]);

        return redirect()->route('penjualan.returns.show', $customerReturn->id)
            ->with('success', 'Inspeksi QC telah dimulai. Silakan periksa kondisi fisik barang dan input keputusan penanganan.');
    }

    /**
     * Menyimpan hasil inspeksi QC dan Keputusan Penanganan per item.
     */
    public function saveQc(CustomerReturn $customerReturn, Request $request): RedirectResponse
    {
        if (!in_array($customerReturn->status, [CustomerReturn::STATUS_RECEIVED, CustomerReturn::STATUS_QC_INSPECTION])) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Pemeriksaan QC hanya dapat diperbarui pada status Diterima atau Inspeksi QC.');
        }

        $validated = $request->validate([
            'warehouse_id'           => ['nullable', 'exists:warehouses,id'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.id'             => ['required', 'exists:customer_return_items,id'],
            'items.*.qc_result'      => ['required', 'in:pass,fail'],
            'items.*.decision'       => ['required', 'in:repair,replace,reject,credit'],
            'items.*.qc_notes'       => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($customerReturn, $validated) {
            // Update gudang penerima jika diubah saat QC
            if (!empty($validated['warehouse_id'])) {
                $customerReturn->update(['warehouse_id' => $validated['warehouse_id']]);
            }

            // Pastikan status menjadi qc_inspection jika sebelumnya masih received
            if ($customerReturn->status === CustomerReturn::STATUS_RECEIVED) {
                $customerReturn->update([
                    'status'          => CustomerReturn::STATUS_QC_INSPECTION,
                    'qc_inspected_by' => Auth::id(),
                    'qc_inspected_at' => now(),
                ]);
            }

            foreach ($validated['items'] as $itemData) {
                $item = CustomerReturnItem::where('customer_return_id', $customerReturn->id)
                    ->where('id', $itemData['id'])
                    ->firstOrFail();

                $item->update([
                    'qc_result' => $itemData['qc_result'],
                    'decision'  => $itemData['decision'],
                    'qc_notes'  => $itemData['qc_notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('penjualan.returns.show', $customerReturn->id)
            ->with('success', 'Hasil inspeksi QC dan Keputusan Penanganan item berhasil disimpan.');
    }

    /**
     * Menyetujui pengajuan Retur Penjualan (Approve).
     */
    public function approve(CustomerReturn $customerReturn): RedirectResponse
    {
        if ($customerReturn->status !== CustomerReturn::STATUS_QC_INSPECTION) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Hanya retur berstatus Inspeksi QC yang dapat disetujui.');
        }

        $customerReturn->loadMissing('customerReturnItems');
        if ($customerReturn->customerReturnItems->isEmpty() || $customerReturn->customerReturnItems->contains(fn ($item) => empty($item->qc_result) || empty($item->decision))) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Semua item retur wajib memiliki hasil pemeriksaan QC (Pass/Fail) dan Keputusan sebelum disetujui.');
        }

        $customerReturn->update([
            'status'      => CustomerReturn::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return redirect()->route('penjualan.returns.show', $customerReturn->id)
            ->with('success', 'Customer Return berhasil Disetujui. Siap diselesaikan untuk pemulihan stok dan pencatatan akuntansi.');
    }

    /**
     * Menolak pengajuan Retur Penjualan (Reject).
     */
    public function reject(CustomerReturn $customerReturn, Request $request): RedirectResponse
    {
        if (in_array($customerReturn->status, [CustomerReturn::STATUS_COMPLETED, CustomerReturn::STATUS_REJECTED])) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Transaksi yang sudah selesai atau ditolak tidak dapat diubah.');
        }

        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min'      => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $reason = (string) $request->input('rejection_reason');
        $updatedNotes = trim(($customerReturn->notes ?? '') . "\n[Ditolak pada " . now()->format('d/m/Y H:i') . "]: " . $reason);

        $customerReturn->update([
            'status'      => CustomerReturn::STATUS_REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'notes'       => $updatedNotes,
        ]);

        return redirect()->route('penjualan.returns.show', $customerReturn->id)
            ->with('info', 'Customer Return telah Ditolak.');
    }

    /**
     * Menyelesaikan Retur Penjualan (Complete).
     * Mengeksekusi pengembalian stok ke gudang penerima dan posting jurnal akuntansi.
     */
    public function complete(CustomerReturn $customerReturn): RedirectResponse
    {
        if ($customerReturn->status !== CustomerReturn::STATUS_APPROVED) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Hanya retur berstatus Disetujui (Approved) yang dapat diselesaikan.');
        }

        if ($customerReturn->stock_restored_at) {
            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Customer Return ini sudah pernah diproses sebelumnya (Anti-Double Execution).');
        }

        try {
            $this->returnService->processCompletion($customerReturn);

            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('success', 'Customer Return berhasil Diselesaikan! Stok barang telah dipulihkan ke gudang dan jurnal akuntansi telah diposting secara seimbang.');
        } catch (Throwable $e) {
            Log::error('CustomerReturnController::complete failed: ' . $e->getMessage(), [
                'exception' => $e,
                'return_id' => $customerReturn->id,
            ]);

            return redirect()->route('penjualan.returns.show', $customerReturn->id)
                ->with('error', 'Gagal menyelesaikan return: ' . $e->getMessage());
        }
    }

    /**
     * Stream formulir resmi dokumen Customer Return dalam format PDF.
     */
    public function pdf(CustomerReturn $customerReturn): Response
    {
        $customerReturn->loadMissing([
            'customer',
            'cabang',
            'invoice',
            'warehouse',
            'customerReturnItems.product.uom',
            'customerReturnItems.invoiceItem',
            'receivedBy',
            'qcInspectedBy',
            'approvedBy',
        ]);

        $doc = $this->docBuilder->customerReturn($customerReturn);

        $pdf = Pdf::loadView('pdf.customer-return', [
            'return' => $customerReturn,
            'doc'    => $doc,
        ])->setPaper('a4', 'portrait');

        $filename = 'Customer_Return_' . str_replace('/', '_', $customerReturn->return_number) . '.pdf';

        return $pdf->stream($filename);
    }
}
