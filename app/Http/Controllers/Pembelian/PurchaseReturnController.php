<?php

namespace App\Http\Controllers\Pembelian;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\StorePurchaseReturnRequest;
use App\Http\Requests\Pembelian\UpdatePurchaseReturnRequest;
use App\Models\Cabang;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Services\PurchaseReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class PurchaseReturnController extends Controller
{
    protected PurchaseReturnService $returnService;

    public function __construct(PurchaseReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    /**
     * Menampilkan daftar Retur Pembelian (Purchase Return & Debit Note).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $supplierId = $request->query('supplier_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $search = $request->query('q');

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $baseQuery = PurchaseReturn::with([
            'purchaseReceipt.supplier',
            'purchaseReceipt.purchaseOrder',
            'cabang',
            'createdBy',
            'approvedBy',
            'purchaseReturnItem.product',
        ]);

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        }

        // Live counter per tab status
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'pending_approval' => (clone $baseQuery)->where('status', 'pending_approval')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        // 4 Kartu KPI
        $kpi = [
            'total_approved_amount' => (float) (clone $baseQuery)
                ->where('status', 'approved')
                ->get()
                ->sum(function (PurchaseReturn $ret) {
                    return $ret->purchaseReturnItem->sum(fn ($item) => (float) $item->qty_returned * (float) $item->unit_price);
                }),
            'this_month_count' => (clone $baseQuery)
                ->whereYear('return_date', now()->year)
                ->whereMonth('return_date', now()->month)
                ->count(),
            'pending_approval_count' => $counts['pending_approval'],
            'draft_count' => $counts['draft'],
        ];

        $query = clone $baseQuery;

        // Filter tab
        match ($tab) {
            'draft' => $query->where('status', 'draft'),
            'pending_approval' => $query->where('status', 'pending_approval'),
            'approved' => $query->where('status', 'approved'),
            'rejected' => $query->where('status', 'rejected'),
            default => null,
        };

        // Filter supplier
        if ($supplierId) {
            $query->whereHas('purchaseReceipt.purchaseOrder', function ($poQ) use ($supplierId) {
                $poQ->where('supplier_id', $supplierId);
            });
        }

        // Filter rentang tanggal
        if ($startDate) {
            $query->whereDate('return_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('return_date', '<=', $endDate);
        }

        // Pencarian q (nomor nota retur, no penerimaan, nama supplier, catatan)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nota_retur', 'LIKE', "%{$search}%")
                  ->orWhere('credit_note_number', 'LIKE', "%{$search}%")
                  ->orWhere('delivery_note', 'LIKE', "%{$search}%")
                  ->orWhereHas('purchaseReceipt', function ($rq) use ($search) {
                      $rq->where('receipt_number', 'LIKE', "%{$search}%")
                         ->orWhereHas('supplier', fn ($sq) => $sq->where('perusahaan', 'LIKE', "%{$search}%"))
                         ->orWhereHas('purchaseOrder', function ($poQ) use ($search) {
                             $poQ->where('po_number', 'LIKE', "%{$search}%")
                                 ->orWhereHas('supplier', fn ($sq) => $sq->where('perusahaan', 'LIKE', "%{$search}%"));
                         });
                  });
            });
        }

        $returns = $query->orderByDesc('return_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('perusahaan')->get(['id', 'code', 'perusahaan']);

        return view('pembelian.retur.index', compact(
            'returns',
            'counts',
            'kpi',
            'tab',
            'suppliers',
            'supplierId',
            'startDate',
            'endDate',
            'search'
        ));
    }

    /**
     * Menampilkan formulir pembuatan Retur Pembelian baru.
     */
    public function create(Request $request): View
    {
        $suppliers = Supplier::orderBy('perusahaan')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $autoNotaRetur = $this->returnService->generateNotaRetur();

        $preselectedSupplierId = $request->query('supplier_id');
        $preselectedReceiptId = $request->query('receipt_id');

        return view('pembelian.retur.create', compact(
            'suppliers',
            'cabangs',
            'autoNotaRetur',
            'preselectedSupplierId',
            'preselectedReceiptId'
        ));
    }

    /**
     * Endpoint AJAX: Mengambil daftar Surat Jalan Masuk / Penerimaan Barang (GRN) milik supplier.
     */
    public function supplierReceipts(Supplier $supplier): JsonResponse
    {
        $receipts = PurchaseReceipt::with(['purchaseOrder'])
            ->whereHas('purchaseOrder', function ($poQ) use ($supplier) {
                $poQ->where('supplier_id', $supplier->id);
            })
            ->whereIn('status', ['completed', 'partial'])
            ->orderByDesc('receipt_date')
            ->get();

        $data = $receipts->map(function (PurchaseReceipt $rcp) {
            $date = $rcp->receipt_date ? Carbon::parse($rcp->receipt_date)->format('d/m/Y') : '-';
            $poNum = $rcp->purchaseOrder?->po_number ?: '-';
            return [
                'id' => $rcp->id,
                'receipt_number' => $rcp->receipt_number,
                'po_number' => $poNum,
                'receipt_date' => $date,
                'display_label' => "{$rcp->receipt_number} (PO: {$poNum} - Tgl: {$date})",
            ];
        });

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'perusahaan' => $supplier->perusahaan,
            ],
            'receipts' => $data,
        ]);
    }

    /**
     * Endpoint AJAX: Mengambil rincian item barang pada Penerimaan Barang (GRN) beserta batas kuantitas retur.
     */
    public function receiptItems(PurchaseReceipt $receipt): JsonResponse
    {
        $receipt->loadMissing([
            'purchaseReceiptItems.product.unitOfMeasure',
            'purchaseReceiptItems.purchaseOrderItem',
            'supplier',
            'purchaseOrder.supplier',
            'warehouse',
            'cabang',
        ]);

        $supplier = $receipt->supplier ?? $receipt->purchaseOrder?->supplier;

        $items = $receipt->purchaseReceiptItems->map(function (PurchaseReceiptItem $item) {
            $product = $item->product;
            $qtyAccepted = (float) ($item->qty_accepted ?? $item->qty_received ?? 0);

            // Hitung kuantitas yang sudah pernah diretur pada retur aktif sebelumnya
            $alreadyReturned = (float) PurchaseReturnItem::where('purchase_receipt_item_id', $item->id)
                ->whereHas('purchaseReturn', function ($q) {
                    $q->whereNotIn('status', ['rejected']);
                })
                ->sum('qty_returned');

            $maxReturnable = max(0.0, round($qtyAccepted - $alreadyReturned, 4));

            $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? $item->unit_price ?? 0);

            return [
                'purchase_receipt_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_code' => $product?->code ?? '-',
                'product_name' => $product?->name ?? 'Produk Tidak Ditemukan',
                'uom_name' => $product?->unitOfMeasure?->name ?? 'Unit',
                'qty_accepted' => $qtyAccepted,
                'already_returned' => $alreadyReturned,
                'max_returnable' => $maxReturnable,
                'unit_price' => $unitPrice,
                'unit_price_formatted' => MoneyHelper::rupiah($unitPrice),
            ];
        })->filter(fn ($line) => $line['max_returnable'] > 0)->values();

        return response()->json([
            'receipt' => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_date' => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('d/m/Y') : '-',
                'supplier_name' => $supplier?->perusahaan ?? '-',
                'supplier_id' => $supplier?->id,
                'warehouse_name' => $receipt->warehouse?->name ?? '-',
                'cabang_name' => $receipt->cabang?->nama ?? '-',
                'cabang_id' => $receipt->cabang_id,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Menyimpan dokumen Retur Pembelian baru.
     */
    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $action = $request->input('action', 'save_draft');

        try {
            $purchaseReturn = DB::transaction(function () use ($validated, $action) {
                $receipt = PurchaseReceipt::findOrFail($validated['purchase_receipt_id']);
                $notaRetur = $validated['nota_retur'] ?? $this->returnService->generateNotaRetur();
                $status = ($action === 'save_and_submit') ? 'pending_approval' : 'draft';
                $cabangId = $validated['cabang_id'] ?? $receipt->cabang_id ?? Auth::user()?->cabang_id ?? Cabang::first()?->id ?? 1;

                $return = PurchaseReturn::create([
                    'nota_retur' => $notaRetur,
                    'purchase_receipt_id' => $receipt->id,
                    'return_date' => $validated['return_date'],
                    'notes' => $validated['notes'] ?? null,
                    'status' => $status,
                    'created_by' => Auth::id(),
                    'cabang_id' => $cabangId,
                    'delivery_note' => $validated['delivery_note'] ?? null,
                    'shipping_details' => $validated['shipping_details'] ?? null,
                    'physical_return_date' => $validated['physical_return_date'] ?? null,
                ]);

                foreach ($validated['items'] as $itemData) {
                    $qtyReturned = (float) ($itemData['qty_returned'] ?? 0);
                    if ($qtyReturned <= 0) {
                        continue;
                    }

                    $rcpItem = PurchaseReceiptItem::with('purchaseOrderItem')->findOrFail($itemData['purchase_receipt_item_id']);
                    $unitPrice = (float) ($rcpItem->purchaseOrderItem?->unit_price ?? $rcpItem->unit_price ?? $itemData['unit_price'] ?? 0);

                    PurchaseReturnItem::create([
                        'purchase_return_id' => $return->id,
                        'purchase_receipt_item_id' => $rcpItem->id,
                        'product_id' => $itemData['product_id'],
                        'qty_returned' => $qtyReturned,
                        'unit_price' => $unitPrice,
                        'reason' => $itemData['reason'] ?? null,
                    ]);
                }

                return $return;
            });

            $msg = ($action === 'save_and_submit')
                ? "Retur Pembelian {$purchaseReturn->nota_retur} berhasil dibuat dan diajukan untuk persetujuan."
                : "Draft Retur Pembelian {$purchaseReturn->nota_retur} berhasil disimpan.";

            return redirect()->route('pembelian.returns.show', $purchaseReturn->id)->with('success', $msg);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Gagal membuat retur pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail lengkap Retur Pembelian & Debit Note.
     */
    public function show(PurchaseReturn $return): View
    {
        $return->loadMissing([
            'purchaseReceipt.supplier',
            'purchaseReceipt.purchaseOrder.supplier',
            'purchaseReceipt.warehouse',
            'cabang',
            'createdBy',
            'approvedBy',
            'rejectedBy',
            'purchaseReturnItem.product.unitOfMeasure',
            'purchaseReturnItem.purchaseReceiptItem.purchaseOrderItem',
        ]);

        // Jurnal Akuntansi Buku Besar yang terkait dengan dokumen retur ini
        $journalEntries = \App\Models\JournalEntry::with('coa')
            ->where('source_type', PurchaseReturn::class)
            ->where('source_id', $return->id)
            ->orderBy('id')
            ->get();

        // Cari Faktur Pembelian (AP) terkait untuk informasi Debit Note
        $accountPayable = $this->returnService->findRelatedAccountPayable($return);

        return view('pembelian.retur.show', compact('return', 'journalEntries', 'accountPayable'));
    }

    /**
     * Menampilkan form edit untuk retur pembelian berstatus Draft atau Ditolak.
     */
    public function edit(PurchaseReturn $return): View|RedirectResponse
    {
        if (!in_array($return->status, ['draft', 'rejected'])) {
            return redirect()->route('pembelian.returns.show', $return->id)
                ->with('error', 'Retur yang sudah disetujui atau diajukan tidak dapat diubah.');
        }

        $return->loadMissing([
            'purchaseReceipt.supplier',
            'purchaseReceipt.purchaseOrder.supplier',
            'purchaseReceipt.purchaseReceiptItems.product.unitOfMeasure',
            'purchaseReturnItem',
        ]);

        $suppliers = Supplier::orderBy('perusahaan')->get();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('pembelian.retur.edit', compact('return', 'suppliers', 'cabangs'));
    }

    /**
     * Memperbarui dokumen retur pembelian draft.
     */
    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $return): RedirectResponse
    {
        if (!in_array($return->status, ['draft', 'rejected'])) {
            return back()->with('error', 'Retur pembelian ini sudah tidak dapat diubah.');
        }

        $validated = $request->validated();
        $action = $request->input('action', 'save_draft');

        try {
            DB::transaction(function () use ($validated, $action, $return) {
                $status = ($action === 'save_and_submit') ? 'pending_approval' : 'draft';

                $return->update([
                    'return_date' => $validated['return_date'],
                    'notes' => $validated['notes'] ?? null,
                    'status' => $status,
                    'delivery_note' => $validated['delivery_note'] ?? null,
                    'shipping_details' => $validated['shipping_details'] ?? null,
                    'physical_return_date' => $validated['physical_return_date'] ?? null,
                ]);

                // Hapus item lama dan buat ulang
                $return->purchaseReturnItem()->forceDelete();

                foreach ($validated['items'] as $itemData) {
                    $qtyReturned = (float) ($itemData['qty_returned'] ?? 0);
                    if ($qtyReturned <= 0) {
                        continue;
                    }

                    $rcpItem = PurchaseReceiptItem::with('purchaseOrderItem')->findOrFail($itemData['purchase_receipt_item_id']);
                    $unitPrice = (float) ($rcpItem->purchaseOrderItem?->unit_price ?? $rcpItem->unit_price ?? $itemData['unit_price'] ?? 0);

                    PurchaseReturnItem::create([
                        'purchase_return_id' => $return->id,
                        'purchase_receipt_item_id' => $rcpItem->id,
                        'product_id' => $itemData['product_id'],
                        'qty_returned' => $qtyReturned,
                        'unit_price' => $unitPrice,
                        'reason' => $itemData['reason'] ?? null,
                    ]);
                }
            });

            $msg = ($action === 'save_and_submit')
                ? "Retur Pembelian {$return->nota_retur} berhasil diperbarui dan diajukan untuk persetujuan."
                : "Draft Retur Pembelian {$return->nota_retur} berhasil diperbarui.";

            return redirect()->route('pembelian.returns.show', $return->id)->with('success', $msg);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memperbarui retur pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus draft retur pembelian.
     */
    public function destroy(PurchaseReturn $return): RedirectResponse
    {
        if (!in_array($return->status, ['draft', 'rejected'])) {
            return back()->with('error', 'Retur yang sudah diajukan atau disetujui tidak dapat dihapus.');
        }

        try {
            DB::transaction(function () use ($return) {
                $return->purchaseReturnItem()->delete();
                $return->delete();
            });

            return redirect()->route('pembelian.returns.index')
                ->with('success', "Draft Retur Pembelian {$return->nota_retur} berhasil dihapus.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menghapus retur pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Mengajukan retur pembelian draft untuk persetujuan (Submit for Approval).
     */
    public function submit(PurchaseReturn $return): RedirectResponse
    {
        if ($return->status !== 'draft') {
            return back()->with('error', 'Hanya retur berstatus draft yang dapat diajukan untuk persetujuan.');
        }

        try {
            $this->returnService->submitForApproval($return);
            return redirect()->route('pembelian.returns.show', $return->id)
                ->with('success', "Retur Pembelian {$return->nota_retur} berhasil diajukan untuk persetujuan.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal mengajukan retur: ' . $e->getMessage());
        }
    }

    /**
     * Menyetujui Retur Pembelian (Approve) — Mengurangi Stok Gudang, Posting GL, & Memotong Hutang AP.
     */
    public function approve(Request $request, PurchaseReturn $return): RedirectResponse
    {
        if (!in_array($return->status, ['draft', 'pending_approval'])) {
            return back()->with('error', 'Retur pembelian hanya dapat disetujui saat statusnya draft atau menunggu persetujuan.');
        }

        try {
            $data = [
                'approval_notes' => $request->input('approval_notes'),
            ];

            $this->returnService->approve($return, $data);

            return redirect()->route('pembelian.returns.show', $return->id)
                ->with('success', "Retur Pembelian {$return->nota_retur} telah disetujui. Pengurangan persediaan gudang, pemotongan hutang dagang, dan jurnal akuntansi telah berhasil diproses.");
        } catch (Throwable $e) {
            Log::error('PurchaseReturnController approve failed', [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Gagal menyetujui retur pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Menolak Retur Pembelian (Reject) dengan alasan penolakan wajib.
     */
    public function reject(Request $request, PurchaseReturn $return): RedirectResponse
    {
        if ($return->status !== 'pending_approval') {
            return back()->with('error', 'Hanya retur berstatus menunggu persetujuan yang dapat ditolak.');
        }

        $request->validate([
            'rejection_notes' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_notes.required' => 'Alasan penolakan wajib diisi.',
            'rejection_notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        try {
            $this->returnService->reject($return, [
                'rejection_notes' => $request->input('rejection_notes'),
            ]);

            return redirect()->route('pembelian.returns.show', $return->id)
                ->with('warning', "Retur Pembelian {$return->nota_retur} telah ditolak.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menolak retur: ' . $e->getMessage());
        }
    }

    /**
     * Memperbarui pelacakan resolusi vendor & Nota Debit / Credit Note dari supplier.
     */
    public function updateResolution(Request $request, PurchaseReturn $return): RedirectResponse
    {
        if ($return->status !== 'approved') {
            return back()->with('error', 'Resolusi dan pelacakan debit note hanya dapat dicatat untuk retur yang telah disetujui.');
        }

        $request->validate([
            'supplier_response' => ['nullable', 'string', 'max:50'],
            'credit_note_number' => ['nullable', 'string', 'max:100'],
            'credit_note_date' => ['nullable', 'date'],
            'credit_note_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_date' => ['nullable', 'date'],
            'refund_method' => ['nullable', 'string', 'max:50'],
            'delivery_note' => ['nullable', 'string', 'max:100'],
            'shipping_details' => ['nullable', 'string', 'max:255'],
            'physical_return_date' => ['nullable', 'date'],
            'tracking_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $return->update([
                'supplier_response' => $request->input('supplier_response') ?: $return->supplier_response,
                'credit_note_number' => $request->input('credit_note_number'),
                'credit_note_date' => $request->input('credit_note_date'),
                'credit_note_amount' => $request->filled('credit_note_amount') ? (float) $request->input('credit_note_amount') : $return->credit_note_amount,
                'refund_amount' => $request->filled('refund_amount') ? (float) $request->input('refund_amount') : $return->refund_amount,
                'refund_date' => $request->input('refund_date'),
                'refund_method' => $request->input('refund_method'),
                'delivery_note' => $request->input('delivery_note') ?: $return->delivery_note,
                'shipping_details' => $request->input('shipping_details') ?: $return->shipping_details,
                'physical_return_date' => $request->input('physical_return_date') ?: $return->physical_return_date,
                'tracking_notes' => $request->input('tracking_notes'),
            ]);

            return redirect()->route('pembelian.returns.show', $return->id)
                ->with('success', "Informasi Nota Debit dan pelacakan pengiriman vendor berhasil disimpan.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menyimpan data resolusi: ' . $e->getMessage());
        }
    }

    /**
     * Cetak dokumen resmi Nota Retur Pembelian & Nota Debit (PDF).
     */
    public function pdf(PurchaseReturn $return): Response
    {
        $return->loadMissing([
            'purchaseReceipt.supplier',
            'purchaseReceipt.purchaseOrder.supplier',
            'purchaseReceipt.warehouse',
            'cabang',
            'createdBy',
            'approvedBy',
            'purchaseReturnItem.product.unitOfMeasure',
            'purchaseReturnItem.purchaseReceiptItem.purchaseOrderItem',
        ]);

        $pdf = Pdf::loadView('pembelian.retur.pdf', compact('return'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("Nota_Retur_{$return->nota_retur}.pdf");
    }
}
