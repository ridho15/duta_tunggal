<?php

namespace App\Http\Controllers\Pembelian;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\StoreVendorPaymentRequest;
use App\Http\Requests\Pembelian\UpdateVendorPaymentRequest;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Observers\VendorPaymentObserver;
use App\Services\LedgerPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class VendorPaymentController extends Controller
{
    protected LedgerPostingService $ledgerService;

    public function __construct(LedgerPostingService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Menampilkan daftar Pembayaran Hutang Vendor.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $supplierId = $request->query('supplier_id');
        $paymentMethod = $request->query('payment_method');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $baseQuery = VendorPayment::with(['supplier', 'cabang', 'coa', 'vendorPaymentDetail.invoice']);

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        }

        // Live counter per status
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'paid' => (clone $baseQuery)->whereIn('status', ['Paid', 'paid'])->count(),
            'partial' => (clone $baseQuery)->whereIn('status', ['Partial', 'partial'])->count(),
            'draft' => (clone $baseQuery)->whereIn('status', ['Draft', 'draft'])->count(),
            'cancelled' => (clone $baseQuery)->whereIn('status', ['Cancelled', 'cancelled'])->count(),
        ];

        // 4 Kartu KPI
        $kpi = [
            'total_paid' => (float) (clone $baseQuery)
                ->whereNotIn('status', ['Draft', 'draft', 'Cancelled', 'cancelled'])
                ->sum('total_payment'),
            'this_month' => (float) (clone $baseQuery)
                ->whereNotIn('status', ['Draft', 'draft', 'Cancelled', 'cancelled'])
                ->whereYear('payment_date', now()->year)
                ->whereMonth('payment_date', now()->month)
                ->sum('total_payment'),
            'draft_count' => $counts['draft'],
            'total_ap_remaining' => (float) AccountPayable::withoutGlobalScopes()
                ->where('remaining', '>', 0)
                ->sum('remaining'),
        ];

        $query = clone $baseQuery;

        // Filter tab
        match ($tab) {
            'paid' => $query->whereIn('status', ['Paid', 'paid']),
            'partial' => $query->whereIn('status', ['Partial', 'partial']),
            'draft' => $query->whereIn('status', ['Draft', 'draft']),
            'cancelled' => $query->whereIn('status', ['Cancelled', 'cancelled']),
            default => null,
        };

        // Filter supplier
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Filter metode pembayaran
        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        // Filter rentang tanggal
        if ($startDate) {
            $query->whereDate('payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payment_date', '<=', $endDate);
        }

        // Pencarian q (nomor pembayaran, no ref transfer, nama perusahaan supplier)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'LIKE', "%{$search}%")
                  ->orWhere('transfer_reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('target_bank_account', 'LIKE', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('perusahaan', 'LIKE', "%{$search}%")
                         ->orWhere('code', 'LIKE', "%{$search}%");
                  });
            });
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('perusahaan')->get(['id', 'code', 'perusahaan']);

        return view('pembelian.payment.index', compact(
            'payments',
            'counts',
            'kpi',
            'tab',
            'suppliers',
            'supplierId',
            'paymentMethod',
            'startDate',
            'endDate',
            'search'
        ));
    }

    /**
     * Menampilkan formulir pembuatan Pembayaran Hutang Vendor.
     */
    public function create(Request $request): View
    {
        $suppliers = Supplier::orderBy('perusahaan')->get();
        $cashBankAccounts = ChartOfAccount::query()
            ->cashBank()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        $cabangs = Cabang::orderBy('nama')->get();

        $preselectedSupplierId = $request->query('supplier_id');
        $preselectedInvoiceId = $request->query('invoice_id');

        return view('pembelian.payment.create', compact(
            'suppliers',
            'cashBankAccounts',
            'cabangs',
            'preselectedSupplierId',
            'preselectedInvoiceId'
        ));
    }

    /**
     * Endpoint AJAX: Mengambil daftar faktur pembelian yang belum lunas milik supplier yang dipilih.
     */
    public function supplierInvoices(Supplier $supplier): JsonResponse
    {
        $invoices = Invoice::with(['accountPayable'])
            ->where(function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                  ->orWhere(function ($sub) use ($supplier) {
                      $sub->where('from_model_type', Supplier::class)
                          ->where('from_model_id', $supplier->id);
                  });
            })
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->orderBy('due_date', 'asc')
            ->get()
            ->filter(function (Invoice $inv) {
                $rem = (float) ($inv->accountPayable?->remaining ?? $inv->total);
                return $rem > 0;
            })
            ->values();

        $data = $invoices->map(function (Invoice $inv) {
            $total = (float) $inv->total;
            $remaining = (float) ($inv->accountPayable?->remaining ?? $total);
            $paid = (float) ($inv->accountPayable?->paid ?? 0);
            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : null;
            $isOverdue = $dueDate ? $dueDate->isPast() && $remaining > 0 : false;

            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'supplier_invoice_number' => $inv->supplier_invoice_number ?: '-',
                'invoice_date' => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-',
                'due_date' => $dueDate ? $dueDate->format('d/m/Y') : '-',
                'is_overdue' => $isOverdue,
                'total' => $total,
                'total_formatted' => MoneyHelper::rupiah($total),
                'paid' => $paid,
                'paid_formatted' => MoneyHelper::rupiah($paid),
                'remaining' => $remaining,
                'remaining_formatted' => MoneyHelper::rupiah($remaining),
                'currency_code' => $inv->currency?->code ?? 'IDR',
                'exchange_rate' => (float) ($inv->exchange_rate ?: 1),
            ];
        });

        // Rekening bank supplier default
        $bankName = $supplier->nama_bank ?? $supplier->bank_name ?? '';
        $bankNum = $supplier->nomor_rekening ?? $supplier->rekening_bank ?? '';
        $holder = $supplier->nama_rekening ?? $supplier->atas_nama ?? $supplier->perusahaan ?? '';
        $targetBank = trim("{$bankName} - {$bankNum} (a.n. {$holder})", ' -()');

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'perusahaan' => $supplier->perusahaan,
                'target_bank_account' => $targetBank,
            ],
            'invoices' => $data,
        ]);
    }

    /**
     * Menyimpan data Pembayaran Hutang Vendor baru.
     */
    public function store(StoreVendorPaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $action = $request->input('action', 'save_and_post');

        try {
            $payment = DB::transaction(function () use ($request, $validated, $action) {
                $proofFilePath = null;
                if ($request->hasFile('proof_file')) {
                    $proofFilePath = $request->file('proof_file')->store('vendor_payments', 'public');
                }

                $selectedInvoices = (array) $validated['selected_invoices'];
                $rawAllocations = (array) $validated['allocations'];
                $invoiceReceipts = [];
                $totalPayment = 0.0;

                foreach ($selectedInvoices as $invId) {
                    $amount = round((float) MoneyHelper::safeParse($rawAllocations[$invId] ?? 0), 2);
                    if ($amount > 0) {
                        $invoiceReceipts[$invId] = $amount;
                        $totalPayment += $amount;
                    }
                }

                // Kalkulasi sisa hutang total dari invoice yang dipilih
                $invoices = Invoice::with('accountPayable')->whereIn('id', $selectedInvoices)->get();
                $totalOutstanding = $invoices->sum(fn ($inv) => (float) ($inv->accountPayable?->remaining ?? $inv->total));

                $isDraft = ($action === 'save_draft');
                $status = $isDraft
                    ? VendorPayment::STATUS_DRAFT
                    : (($totalPayment >= ($totalOutstanding - 1.00)) ? VendorPayment::STATUS_PAID : VendorPayment::STATUS_PARTIAL);

                $exchangeRate = (float) ($invoices->first()?->exchange_rate ?: 1);
                $currencyId = $invoices->first()?->currency_id;

                $selectedInvoicesDetails = [];
                foreach ($invoiceReceipts as $invId => $amt) {
                    $selectedInvoicesDetails[] = [
                        'invoice_id' => (int) $invId,
                        'amount' => (float) $amt,
                    ];
                }

                $payment = VendorPayment::withoutEvents(function () use ($validated, $proofFilePath, $selectedInvoicesDetails, $invoiceReceipts, $totalPayment, $exchangeRate, $currencyId, $status) {
                    return VendorPayment::create([
                        'payment_number' => VendorPayment::generatePaymentNumber(),
                        'supplier_id' => $validated['supplier_id'],
                        'cabang_id' => $validated['cabang_id'] ?? Auth::user()?->cabang_id ?? Cabang::first()?->id ?? 1,
                        'payment_date' => $validated['payment_date'],
                        'payment_method' => $validated['payment_method'],
                        'coa_id' => $validated['coa_id'],
                        'target_bank_account' => $validated['target_bank_account'] ?? null,
                        'transfer_reference_number' => $validated['transfer_reference_number'] ?? null,
                        'proof_file' => $proofFilePath,
                        'notes' => $validated['notes'] ?? null,
                        'selected_invoices' => $selectedInvoicesDetails,
                        'invoice_receipts' => $invoiceReceipts,
                        'total_payment' => $totalPayment,
                        'total_payment_idr' => round($totalPayment * $exchangeRate, 2),
                        'currency_id' => $currencyId,
                        'exchange_rate' => $exchangeRate,
                        'status' => $status,
                    ]);
                });

                // Buat rincian item pembayaran (VendorPaymentDetail)
                foreach ($invoiceReceipts as $invId => $amt) {
                    VendorPaymentDetail::create([
                        'vendor_payment_id' => $payment->id,
                        'invoice_id' => $invId,
                        'currency_id' => $currencyId,
                        'exchange_rate' => $exchangeRate,
                        'method' => $validated['payment_method'],
                        'amount' => $amt,
                        'amount_idr' => round($amt * $exchangeRate, 2),
                        'coa_id' => $validated['coa_id'],
                        'payment_date' => $validated['payment_date'],
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                // Jika langsung diposting, sinkronisasikan AP, status Invoice, dan buat jurnal GL
                if (!$isDraft) {
                    $observer = new VendorPaymentObserver();
                    $observer->updateAccountPayableAndInvoiceStatus($payment);

                    // Buat Jurnal Buku Besar (GL)
                    $this->ledgerService->postVendorPayment($payment);
                }

                return $payment;
            });

            $msg = ($action === 'save_draft')
                ? "Draft Pembayaran {$payment->payment_number} berhasil disimpan."
                : "Pembayaran {$payment->payment_number} senilai " . MoneyHelper::rupiah($payment->total_payment) . " berhasil diposting dan dialokasikan ke hutang dagang.";

            return redirect()->route('pembelian.payments.show', $payment->id)->with('success', $msg);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memproses pembayaran vendor: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail Pembayaran Hutang Vendor.
     */
    public function show(VendorPayment $payment): View
    {
        $payment->loadMissing([
            'supplier',
            'cabang',
            'coa',
            'vendorPaymentDetail.invoice.accountPayable',
            'journalEntries.coa',
        ]);

        return view('pembelian.payment.show', compact('payment'));
    }

    /**
     * Menampilkan form edit pembayaran draft.
     */
    public function edit(VendorPayment $payment): View|RedirectResponse
    {
        if (strtolower((string) $payment->status) !== 'draft') {
            return redirect()->route('pembelian.payments.show', $payment->id)
                ->with('error', 'Pembayaran yang sudah diposting tidak dapat diubah.');
        }

        $payment->loadMissing(['supplier', 'vendorPaymentDetail.invoice.accountPayable']);

        $suppliers = Supplier::orderBy('perusahaan')->get();
        $cashBankAccounts = ChartOfAccount::query()
            ->cashBank()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('pembelian.payment.edit', compact('payment', 'suppliers', 'cashBankAccounts', 'cabangs'));
    }

    /**
     * Memperbarui pembayaran draft.
     */
    public function update(UpdateVendorPaymentRequest $request, VendorPayment $payment): RedirectResponse
    {
        if (strtolower((string) $payment->status) !== 'draft') {
            return redirect()->route('pembelian.payments.show', $payment->id)
                ->with('error', 'Pembayaran yang sudah diposting tidak dapat diubah.');
        }

        $validated = $request->validated();
        $action = $request->input('action', 'save_draft');

        try {
            DB::transaction(function () use ($request, $validated, $action, $payment) {
                if ($request->hasFile('proof_file')) {
                    if ($payment->proof_file && Storage::disk('public')->exists($payment->proof_file)) {
                        Storage::disk('public')->delete($payment->proof_file);
                    }
                    $payment->proof_file = $request->file('proof_file')->store('vendor_payments', 'public');
                }

                $selectedInvoices = (array) $validated['selected_invoices'];
                $rawAllocations = (array) $validated['allocations'];
                $invoiceReceipts = [];
                $totalPayment = 0.0;

                foreach ($selectedInvoices as $invId) {
                    $amount = round((float) MoneyHelper::safeParse($rawAllocations[$invId] ?? 0), 2);
                    if ($amount > 0) {
                        $invoiceReceipts[$invId] = $amount;
                        $totalPayment += $amount;
                    }
                }

                $invoices = Invoice::with('accountPayable')->whereIn('id', $selectedInvoices)->get();
                $totalOutstanding = $invoices->sum(fn ($inv) => (float) ($inv->accountPayable?->remaining ?? $inv->total));

                $isDraft = ($action === 'save_draft');
                $status = $isDraft
                    ? VendorPayment::STATUS_DRAFT
                    : (($totalPayment >= ($totalOutstanding - 1.00)) ? VendorPayment::STATUS_PAID : VendorPayment::STATUS_PARTIAL);

                $exchangeRate = (float) ($invoices->first()?->exchange_rate ?: 1);
                $currencyId = $invoices->first()?->currency_id;

                $selectedInvoicesDetails = [];
                foreach ($invoiceReceipts as $invId => $amt) {
                    $selectedInvoicesDetails[] = [
                        'invoice_id' => (int) $invId,
                        'amount' => (float) $amt,
                    ];
                }

                $payment->fill([
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'coa_id' => $validated['coa_id'],
                    'target_bank_account' => $validated['target_bank_account'] ?? null,
                    'transfer_reference_number' => $validated['transfer_reference_number'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'selected_invoices' => $selectedInvoicesDetails,
                    'invoice_receipts' => $invoiceReceipts,
                    'total_payment' => $totalPayment,
                    'total_payment_idr' => round($totalPayment * $exchangeRate, 2),
                    'currency_id' => $currencyId,
                    'exchange_rate' => $exchangeRate,
                    'status' => $status,
                ]);
                $payment->saveQuietly();

                // Re-create payment details
                $payment->vendorPaymentDetail()->forceDelete();

                foreach ($invoiceReceipts as $invId => $amt) {
                    VendorPaymentDetail::create([
                        'vendor_payment_id' => $payment->id,
                        'invoice_id' => $invId,
                        'currency_id' => $currencyId,
                        'exchange_rate' => $exchangeRate,
                        'method' => $validated['payment_method'],
                        'amount' => $amt,
                        'amount_idr' => round($amt * $exchangeRate, 2),
                        'coa_id' => $validated['coa_id'],
                        'payment_date' => $validated['payment_date'],
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                if (!$isDraft) {
                    $observer = new VendorPaymentObserver();
                    $observer->updateAccountPayableAndInvoiceStatus($payment);
                    $this->ledgerService->postVendorPayment($payment);
                }
            });

            $msg = ($action === 'save_draft')
                ? "Draft Pembayaran {$payment->payment_number} berhasil diperbarui."
                : "Pembayaran {$payment->payment_number} berhasil diposting ke Buku Besar.";

            return redirect()->route('pembelian.payments.show', $payment->id)->with('success', $msg);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memperbarui pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus pembayaran yang masih berstatus Draft.
     */
    public function destroy(VendorPayment $payment): RedirectResponse
    {
        if (strtolower((string) $payment->status) !== 'draft') {
            return back()->with('error', 'Pembayaran yang sudah diproses atau diposting tidak dapat dihapus.');
        }

        try {
            DB::transaction(function () use ($payment) {
                if ($payment->proof_file && Storage::disk('public')->exists($payment->proof_file)) {
                    Storage::disk('public')->delete($payment->proof_file);
                }
                $payment->vendorPaymentDetail()->delete();
                $payment->delete();
            });

            return redirect()->route('pembelian.payments.index')
                ->with('success', "Draft pembayaran {$payment->payment_number} berhasil dihapus.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menghapus pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Memposting pembayaran draft ke Buku Besar (GL) dan memotong saldo AP.
     */
    public function post(VendorPayment $payment): RedirectResponse
    {
        if (strtolower((string) $payment->status) !== 'draft') {
            return back()->with('error', 'Hanya pembayaran berstatus Draft yang dapat diposting.');
        }

        try {
            DB::transaction(function () use ($payment) {
                $details = $payment->vendorPaymentDetail()->get();
                if ($details->isEmpty()) {
                    throw new \RuntimeException('Pembayaran tidak memiliki rincian alokasi tagihan invoice.');
                }

                $invoiceIds = $details->pluck('invoice_id')->toArray();
                $invoices = Invoice::with('accountPayable')->whereIn('id', $invoiceIds)->get();
                $totalOutstanding = $invoices->sum(fn ($inv) => (float) ($inv->accountPayable?->remaining ?? $inv->total));

                $newStatus = ($payment->total_payment >= ($totalOutstanding - 1.00))
                    ? VendorPayment::STATUS_PAID
                    : VendorPayment::STATUS_PARTIAL;

                $payment->status = $newStatus;
                $payment->saveQuietly();

                $observer = new VendorPaymentObserver();
                $observer->updateAccountPayableAndInvoiceStatus($payment);
                $this->ledgerService->postVendorPayment($payment);
            });

            return redirect()->route('pembelian.payments.show', $payment->id)
                ->with('success', "Pembayaran {$payment->payment_number} berhasil diposting ke Buku Besar.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal memposting pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Membatalkan pembayaran posted (Reversal AP & GL).
     */
    public function cancel(Request $request, VendorPayment $payment): RedirectResponse
    {
        $status = strtolower((string) $payment->status);
        if ($status === 'cancelled') {
            return back()->with('error', 'Pembayaran ini sudah dibatalkan sebelumnya.');
        }

        $request->validate([
            'cancel_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        try {
            DB::transaction(function () use ($request, $payment) {
                $observer = new VendorPaymentObserver();

                // 1. Reversalkan efek Account Payable dan status Invoice
                $observer->reverseAccountPayableAndInvoiceStatus($payment);

                // 2. Reversalkan Jurnal Akuntansi Buku Besar (GL)
                $payment->journalEntries()->delete();

                // 3. Update status pembayaran menjadi Cancelled
                $payment->status = VendorPayment::STATUS_CANCELLED;
                $payment->notes = trim(($payment->notes ? $payment->notes . "\n" : '') . '[DIBATALKAN] Alasan: ' . $request->input('cancel_reason'));
                $payment->saveQuietly();
            });

            return redirect()->route('pembelian.payments.show', $payment->id)
                ->with('success', "Pembayaran {$payment->payment_number} berhasil dibatalkan. Saldo Hutang Dagang dan Jurnal Buku Besar telah dipulihkan.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membatalkan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Cetak dokumen resmi Bukti Pengeluaran Kas/Bank (Vendor Payment Voucher) PDF.
     */
    public function pdf(VendorPayment $payment): Response
    {
        $payment->loadMissing([
            'supplier',
            'cabang',
            'coa',
            'vendorPaymentDetail.invoice',
        ]);

        $pdf = Pdf::loadView('pembelian.payment.pdf', compact('payment'))
            ->setPaper('a4', 'portrait');

        $filename = "Voucher_Pembayaran_{$payment->payment_number}.pdf";

        return $pdf->stream($filename);
    }
}
