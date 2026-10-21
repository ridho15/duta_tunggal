<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Services\CreditNoteService;
use App\Services\DocumentPrintBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CreditNoteController extends Controller
{
    protected CreditNoteService $creditNoteService;
    protected DocumentPrintBuilder $docBuilder;

    public function __construct(
        CreditNoteService $creditNoteService,
        DocumentPrintBuilder $docBuilder
    ) {
        $this->creditNoteService = $creditNoteService;
        $this->docBuilder = $docBuilder;
    }

    /**
     * Menampilkan daftar transaksi Nota Kredit (Credit Note).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $type = $request->query('type');
        $cabangId = $request->query('cabang_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Query dasar
        $baseQuery = CreditNote::query()
            ->with([
                'customer',
                'invoice',
                'customerReturn',
                'createdBy',
                'issuedBy',
            ]);

        // Filter Cabang
        if (! empty($cabangId)) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Filter Tanggal Nota Kredit
        if (! empty($dateFrom)) {
            $baseQuery->whereDate('credit_date', '>=', $dateFrom);
        }
        if (! empty($dateTo)) {
            $baseQuery->whereDate('credit_date', '<=', $dateTo);
        }

        // Filter Jenis
        if (! empty($type) && array_key_exists($type, CreditNote::TYPE_LABELS)) {
            $baseQuery->where('type', $type);
        }

        // Hitung KPI (berdasarkan base query filter tanggal & cabang)
        $kpiQuery = clone $baseQuery;
        $totalCount = (clone $kpiQuery)->count();
        $totalAmount = (float) (clone $kpiQuery)->sum('total');

        $draftCount = (clone $kpiQuery)->where('status', CreditNote::STATUS_DRAFT)->count();
        $draftAmount = (float) (clone $kpiQuery)->where('status', CreditNote::STATUS_DRAFT)->sum('total');

        $issuedCount = (clone $kpiQuery)->where('status', CreditNote::STATUS_ISSUED)->count();
        $issuedAmount = (float) (clone $kpiQuery)->where('status', CreditNote::STATUS_ISSUED)->sum('total');
        $appliedToArTotal = (float) (clone $kpiQuery)->where('status', CreditNote::STATUS_ISSUED)->sum('applied_to_ar');
        $appliedToDepositTotal = (float) (clone $kpiQuery)->where('status', CreditNote::STATUS_ISSUED)->sum('applied_to_deposit');

        // Filter Tab Status
        if ($tab === 'draft') {
            $baseQuery->where('status', CreditNote::STATUS_DRAFT);
        } elseif ($tab === 'issued') {
            $baseQuery->where('status', CreditNote::STATUS_ISSUED);
        }

        // Pencarian Teks
        if (! empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('tax_document_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', function ($iq) use ($search) {
                        $iq->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        // Pagination
        $creditNotes = $baseQuery
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama']);

        return view('penjualan.credit-notes.index', [
            'creditNotes' => $creditNotes,
            'tab' => $tab,
            'search' => $search,
            'type' => $type,
            'cabangId' => $cabangId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'cabangs' => $cabangs,
            'kpis' => [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'draft_count' => $draftCount,
                'draft_amount' => $draftAmount,
                'issued_count' => $issuedCount,
                'issued_amount' => $issuedAmount,
                'applied_to_ar_total' => $appliedToArTotal,
                'applied_to_deposit_total' => $appliedToDepositTotal,
            ],
        ]);
    }

    /**
     * Menampilkan form pembuatan draf Nota Kredit baru.
     */
    public function create(Request $request): View
    {
        $customerId = $request->query('customer_id');
        $invoiceId = $request->query('invoice_id');
        $returnId = $request->query('return_id');
        $type = $request->query('type', CreditNote::TYPE_CORRECTION);

        $selectedCustomer = null;
        $selectedInvoice = null;
        $selectedReturn = null;

        if (! empty($returnId)) {
            $selectedReturn = CustomerReturn::with(['customer', 'invoice'])->find($returnId);
            if ($selectedReturn) {
                $customerId = $selectedReturn->customer_id;
                $invoiceId = $selectedReturn->invoice_id;
                $type = CreditNote::TYPE_RETURN;
            }
        }

        if (! empty($customerId)) {
            $selectedCustomer = Customer::find($customerId);
        }

        if (! empty($invoiceId)) {
            $selectedInvoice = Invoice::with(['accountReceivable', 'invoiceItem.product'])->find($invoiceId);
            if ($selectedInvoice && ! $selectedCustomer) {
                $selectedCustomer = $selectedInvoice->customer;
                $customerId = $selectedCustomer?->id;
            }
        }

        $customers = Customer::orderBy('name')->get(['id', 'name', 'code']);

        return view('penjualan.credit-notes.create', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'selectedInvoice' => $selectedInvoice,
            'selectedReturn' => $selectedReturn,
            'presetType' => $type,
            'presetReason' => $selectedReturn ? "Retur {$selectedReturn->return_number}: ".trim((string) $selectedReturn->reason) : '',
        ]);
    }

    /**
     * Endpoint AJAX: Mengambil daftar Invoice penjualan yang masih dapat dikreditkan untuk Customer tertentu.
     */
    public function customerInvoices(Customer $customer): JsonResponse
    {
        $customerId = $customer->id;
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
            ->whereIn('status', CreditNoteService::POSTED_STATUSES)
            ->with(['invoiceItem.product', 'accountReceivable'])
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($invoices as $inv) {
            $creditableMap = $this->creditNoteService->creditableQuantities($inv, includeDrafts: true);
            $hasCreditableItem = false;
            foreach ($creditableMap as $itemId => $remQty) {
                if ($remQty > 0) {
                    $hasCreditableItem = true;
                    break;
                }
            }

            $shippingCreditable = $this->creditNoteService->creditableShipping($inv);
            if (! $hasCreditableItem && $shippingCreditable <= 0.05) {
                continue; // invoice tidak memiliki item/ongkir yang tersisa untuk dikreditkan
            }

            $ar = $inv->accountReceivable;
            $data[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->translatedFormat('d M Y') : '-',
                'status' => $inv->status,
                'total' => (float) $inv->total,
                'total_formatted' => 'Rp ' . number_format((float) $inv->total, 0, ',', '.'),
                'remaining_ar' => (float) ($ar?->remaining ?? 0),
                'remaining_ar_formatted' => 'Rp ' . number_format((float) ($ar?->remaining ?? 0), 0, ',', '.'),
                'shipping_cost' => (float) ($inv->other_fee_total ?? 0),
                'creditable_shipping' => $shippingCreditable,
            ];
        }

        return response()->json([
            'success' => true,
            'invoices' => $data,
        ]);
    }

    /**
     * Endpoint AJAX: Mengambil rincian item invoice dan sisa kuantitas yang dapat dikreditkan.
     */
    public function invoiceDetails(Invoice $invoice): JsonResponse
    {
        $invoice->load(['invoiceItem.product', 'accountReceivable']);
        $creditableMap = $this->creditNoteService->creditableQuantities($invoice, includeDrafts: true);
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();

        $items = [];
        foreach ($invoice->invoiceItem as $item) {
            $remQty = (float) ($creditableMap[$item->id] ?? 0);
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_code' => $item->product?->code ?? '-',
                'product_name' => $item->product?->name ?? ($item->description ?: 'Barang #' . $item->id),
                'unit' => $item->product?->unit ?? 'Pcs',
                'original_qty' => (float) $item->quantity,
                'creditable_qty' => $remQty,
                'unit_price' => (float) $item->unit_price,
                'unit_price_formatted' => 'Rp ' . number_format((float) $item->unit_price, 0, ',', '.'),
                'subtotal' => (float) $item->subtotal,
                'tax_amount' => (float) $item->tax_amount,
                'total' => (float) $item->total,
            ];
        }

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->translatedFormat('d M Y') : '-',
                'subtotal' => (float) $invoice->subtotal,
                'tax_amount' => (float) $invoice->tax_amount,
                'shipping_cost' => (float) ($invoice->other_fee_total ?? 0),
                'total' => (float) $invoice->total,
                'total_formatted' => 'Rp ' . number_format((float) $invoice->total, 0, ',', '.'),
                'ar_remaining' => (float) ($ar?->remaining ?? 0),
                'ar_remaining_formatted' => 'Rp ' . number_format((float) ($ar?->remaining ?? 0), 0, ',', '.'),
                'creditable_shipping' => $this->creditNoteService->creditableShipping($invoice),
            ],
            'items' => $items,
        ]);
    }

    /**
     * Menyimpan dokumen draf Nota Kredit baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'type' => 'required|in:' . implode(',', array_keys(CreditNote::TYPE_LABELS)),
            'reason' => 'required|string|min:10',
            'credit_date' => 'nullable|date',
            'include_shipping' => 'nullable|boolean',
            'customer_return_id' => 'nullable|exists:customer_returns,id',
            'quantities' => 'nullable|array',
            'quantities.*' => 'nullable|numeric|min:0',
        ], [
            'invoice_id.required' => 'Faktur penjualan rujukan wajib dipilih.',
            'type.required' => 'Jenis Nota Kredit wajib dipilih.',
            'reason.required' => 'Alasan pembuatan Nota Kredit wajib diisi.',
            'reason.min' => 'Alasan Nota Kredit minimal 10 karakter.',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $user = Auth::user();

        try {
            $quantities = [];
            if ($validated['type'] !== CreditNote::TYPE_CANCELLATION) {
                foreach ((array) ($validated['quantities'] ?? []) as $itemId => $qty) {
                    $qtyFloat = (float) $qty;
                    if ($qtyFloat > 0) {
                        $quantities[(int) $itemId] = $qtyFloat;
                    }
                }
            }

            $options = [
                'credit_date' => $validated['credit_date'] ?? now()->toDateString(),
                'include_shipping' => (bool) ($validated['include_shipping'] ?? false),
                'customer_return_id' => $validated['customer_return_id'] ?? null,
            ];

            $creditNote = $this->creditNoteService->draft(
                $invoice,
                $validated['type'],
                $quantities,
                $validated['reason'],
                $options,
                $user
            );

            return redirect()
                ->route('penjualan.credit-notes.show', $creditNote->id)
                ->with('success', "Draf Nota Kredit {$creditNote->credit_note_number} berhasil dibuat. Silakan tinjau dan terbitkan.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        } catch (Throwable $e) {
            Log::error('Error storing Credit Note draft: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    /**
     * Aksi cepat: Membuat draf Nota Kredit dari dokumen Retur Penjualan (Customer Return).
     */
    public function storeFromReturn(CustomerReturn $customerReturn): RedirectResponse
    {
        $user = Auth::user();

        try {
            $creditNote = $this->creditNoteService->draftFromReturn($customerReturn, $user);

            return redirect()
                ->route('penjualan.credit-notes.show', $creditNote->id)
                ->with('success', "Draf Nota Kredit {$creditNote->credit_note_number} berhasil dibuat dari Retur {$customerReturn->return_number}.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Error drafting Credit Note from return: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()
                ->back()
                ->with('error', 'Gagal membuat Nota Kredit dari retur: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail transaksi Nota Kredit (Credit Note).
     */
    public function show(CreditNote $creditNote): View
    {
        $creditNote->load([
            'customer',
            'invoice.invoiceItem.product',
            'customerReturn',
            'items.product',
            'createdBy',
            'issuedBy',
            'journalEntries.coa',
        ]);

        $ar = AccountReceivable::where('invoice_id', $creditNote->invoice_id)->first();

        // Hitung estimasi dampak (Impact Preview)
        $impact = null;
        if ($creditNote->isDraft() && $creditNote->invoice && $ar) {
            $impact = $this->creditNoteService->impact($creditNote, $creditNote->invoice, $ar);
        }

        return view('penjualan.credit-notes.show', [
            'creditNote' => $creditNote,
            'ar' => $ar,
            'impact' => $impact,
        ]);
    }

    /**
     * Menerbitkan Nota Kredit (Finalisasi, Jurnal Balik, Alokasi Piutang & Deposit).
     */
    public function issue(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $request->validate([
            'tax_document_number' => 'nullable|string|max:100',
            'override_reason' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        try {
            $options = [
                'tax_document_number' => $request->input('tax_document_number'),
                'override_reason' => $request->input('override_reason'),
            ];

            $this->creditNoteService->issue($creditNote, $user, $options);

            return redirect()
                ->route('penjualan.credit-notes.show', $creditNote->id)
                ->with('success', "Nota Kredit {$creditNote->credit_note_number} berhasil diterbitkan! Jurnal akuntansi telah diposting dan saldo piutang telah disesuaikan.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Error issuing Credit Note: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()
                ->back()
                ->with('error', 'Gagal menerbitkan Nota Kredit: ' . $e->getMessage());
        }
    }

    /**
     * Mengisi / memperbarui nomor Nota Retur Pajak (metadata perpajakan).
     */
    public function updateTaxDocument(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $request->validate([
            'tax_document_number' => 'nullable|string|max:100',
        ]);

        $user = Auth::user();

        try {
            $this->creditNoteService->setTaxDocumentNumber(
                $creditNote,
                $request->input('tax_document_number'),
                $user
            );

            return redirect()
                ->back()
                ->with('success', 'Nomor Nota Retur Pajak berhasil diperbarui.');
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Gagal memperbarui nomor faktur pajak retur: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus draf Nota Kredit (hanya draf yang dapat dihapus, issued terkunci final).
     */
    public function destroy(CreditNote $creditNote): RedirectResponse
    {
        try {
            $cnNumber = $creditNote->credit_note_number;
            $this->creditNoteService->deleteDraft($creditNote);

            return redirect()
                ->route('penjualan.credit-notes.index')
                ->with('success', "Draf Nota Kredit {$cnNumber} berhasil dihapus.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Gagal menghapus draf: ' . $e->getMessage());
        }
    }

    /**
     * Streaming PDF Nota Kredit untuk pratinjau / pencetakan.
     */
    public function pdf(CreditNote $creditNote): Response
    {
        try {
            $creditNote->load([
                'customer',
                'invoice',
                'items.product',
                'createdBy',
                'issuedBy',
            ]);

            $doc = $this->docBuilder->creditNote($creditNote);

            $pdf = Pdf::loadView('pdf.credit-note', [
                'creditNote' => $creditNote,
                'doc' => $doc,
            ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

            return $pdf->stream("Nota-Kredit-{$creditNote->credit_note_number}.pdf");
        } catch (Throwable $e) {
            Log::error('Error streaming Credit Note PDF: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->view('errors.custom', [
                'message' => 'Gagal membuat dokumen PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}
