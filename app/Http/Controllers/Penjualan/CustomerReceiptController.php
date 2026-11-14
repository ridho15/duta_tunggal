<?php

namespace App\Http\Controllers\Penjualan;

use App\Enums\PaymentStatus;
use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Support\CustomerPicker;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Observers\CustomerReceiptObserver;
use App\Services\CustomerReceiptAllocator;
use App\Services\CustomerReceiptCancellation;
use App\Services\CustomerReceiptReference;
use App\Services\DepositNumberGenerator;
use App\Services\DocumentPrintBuilder;
use App\Services\LedgerPostingService;
use App\Support\CustomerReceiptAccounts;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CustomerReceiptController extends Controller
{
    protected CustomerReceiptAllocator $allocator;
    protected CustomerReceiptCancellation $cancellation;
    protected CustomerReceiptReference $referenceService;
    protected LedgerPostingService $ledgerService;
    protected DocumentPrintBuilder $docBuilder;

    public function __construct(
        CustomerReceiptAllocator $allocator,
        CustomerReceiptCancellation $cancellation,
        CustomerReceiptReference $referenceService,
        LedgerPostingService $ledgerService,
        DocumentPrintBuilder $docBuilder
    ) {
        $this->allocator = $allocator;
        $this->cancellation = $cancellation;
        $this->referenceService = $referenceService;
        $this->ledgerService = $ledgerService;
        $this->docBuilder = $docBuilder;
    }

    /**
     * Menampilkan daftar Penerimaan Customer / Kwitansi Pelunasan Piutang.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');
        $paymentMethod = $request->query('payment_method');

        $baseQuery = CustomerReceipt::with(['customer', 'cabang', 'coa', 'customerReceiptItem.invoice']);

        // Live counter tabs
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'paid' => (clone $baseQuery)->whereIn('status', ['Paid', 'paid'])->count(),
            'partial' => (clone $baseQuery)->whereIn('status', ['Partial', 'partial'])->count(),
            'draft' => (clone $baseQuery)->whereIn('status', ['Draft', 'draft'])->count(),
            'cancelled' => (clone $baseQuery)->whereIn('status', ['Cancelled', 'cancelled'])->count(),
        ];

        // KPI agregasi
        $totalReceived = (float) (clone $baseQuery)
            ->whereNotIn('status', ['Cancelled', 'cancelled'])
            ->sum('total_payment');

        $thisMonthReceived = (float) (clone $baseQuery)
            ->whereNotIn('status', ['Cancelled', 'cancelled'])
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month)
            ->sum('total_payment');

        $totalArPaid = (float) AccountReceivable::withoutGlobalScopes()->sum('paid');
        $totalArRemaining = (float) AccountReceivable::withoutGlobalScopes()->where('remaining', '>', 0)->sum('remaining');

        $query = clone $baseQuery;

        // Filter tab status
        if ($tab === 'paid') {
            $query->whereIn('status', ['Paid', 'paid']);
        } elseif ($tab === 'partial') {
            $query->whereIn('status', ['Partial', 'partial']);
        } elseif ($tab === 'draft') {
            $query->whereIn('status', ['Draft', 'draft']);
        } elseif ($tab === 'cancelled') {
            $query->whereIn('status', ['Cancelled', 'cancelled']);
        }

        // Filter cabang
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        // Filter metode pembayaran
        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        // Filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customerReceiptItem.invoice', function ($iq) use ($search) {
                        $iq->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        $receipts = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->get();

        return view('penjualan.receipts.index', compact(
            'receipts',
            'counts',
            'tab',
            'search',
            'cabangs',
            'cabangId',
            'paymentMethod',
            'totalReceived',
            'thisMonthReceived',
            'totalArPaid',
            'totalArRemaining'
        ));
    }

    /**
     * Menampilkan form pembuatan Penerimaan Customer (Customer Receipt).
     */
    public function create(Request $request): View
    {
        $preselectedInvoiceId = $request->query('invoice_id');
        $preselectedCustomerId = $request->query('customer_id');

        $initialInvoice = null;
        if ($preselectedInvoiceId) {
            $initialInvoice = Invoice::withoutGlobalScopes()->with(['accountReceivable.customer'])->find($preselectedInvoiceId);
            if ($initialInvoice && !$preselectedCustomerId) {
                $preselectedCustomerId = $initialInvoice->accountReceivable?->customer_id ?? $initialInvoice->customer?->id;
            }
        }

        // Master customer tidak dimuat: customer terpilih + 25 pelanggan berpiutang teratas; sisanya dicari lewat AJAX (debtors_first).
        $customers = CustomerPicker::prefill([$preselectedCustomerId], debtors: 25);

        // Data akun COA yang diperbolehkan per metode pembayaran
        $methods = ['Cash', 'Transfer', 'Giro', 'Cheque', 'Deposit'];
        $coasByMethod = [];
        $defaultCoaByMethod = [];
        foreach ($methods as $method) {
            $coasByMethod[$method] = CustomerReceiptAccounts::options($method);
            $defaultCoaByMethod[$method] = CustomerReceiptAccounts::defaultId($method);
        }

        // Siapkan initial invoices jika customer telah dipilih
        $initialInvoices = [];
        $customerDepositBalance = 0;
        if ($preselectedCustomerId) {
            $initialInvoices = $this->getInvoicesForCustomer((int) $preselectedCustomerId);
            $customerDepositBalance = (float) Deposit::where('from_model_type', Customer::class)
                ->where('from_model_id', $preselectedCustomerId)
                ->where('status', 'active')
                ->where('remaining_amount', '>', 0)
                ->sum('remaining_amount');
        }

        return view('penjualan.receipts.create', compact(
            'customers',
            'coasByMethod',
            'defaultCoaByMethod',
            'preselectedCustomerId',
            'preselectedInvoiceId',
            'initialInvoices',
            'customerDepositBalance'
        ));
    }

    /**
     * Endpoint JSON untuk memuat tagihan aktif dan saldo deposit seorang customer.
     */
    public function customerInvoices(Customer $customer): JsonResponse
    {
        $invoices = $this->getInvoicesForCustomer($customer->id);

        $depositBalance = (float) Deposit::where('from_model_type', Customer::class)
            ->where('from_model_id', $customer->id)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone ?? '-',
            ],
            'deposit_balance' => $depositBalance,
            'deposit_balance_formatted' => 'Rp ' . number_format($depositBalance, 0, ',', '.'),
            'invoices' => $invoices,
        ]);
    }

    /**
     * Ambil data invoiceable untuk customer tertentu dalam format array siap pakai.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getInvoicesForCustomer(int $customerId): array
    {
        return Invoice::invoiceableForCustomer($customerId)
            ->with(['cabang', 'accountReceivable'])
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (Invoice $inv) {
                $ar = $inv->accountReceivable;
                $remaining = $ar ? (float) $ar->remaining : (float) $inv->total;
                $paid = $ar ? (float) $ar->paid : 0.0;
                $invDate = $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-';
                $dueDate = $inv->due_date ? Carbon::parse($inv->due_date)->format('d/m/Y') : '-';
                $isOverdue = $inv->due_date && Carbon::parse($inv->due_date)->isPast() && $remaining > 0;

                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $invDate,
                    'due_date' => $dueDate,
                    'is_overdue' => $isOverdue,
                    'total' => (float) $inv->total,
                    'total_formatted' => 'Rp ' . number_format((float) $inv->total, 0, ',', '.'),
                    'paid' => $paid,
                    'paid_formatted' => 'Rp ' . number_format($paid, 0, ',', '.'),
                    'remaining' => $remaining,
                    'remaining_formatted' => 'Rp ' . number_format($remaining, 0, ',', '.'),
                    'cabang_id' => $inv->cabang_id,
                    'cabang_nama' => $inv->cabang?->nama ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Memproses penyimpanan transaksi Penerimaan Customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:Cash,Transfer,Giro,Cheque,Deposit'],
            'coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'proof' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Kosong + deposit_amount > 0 = Titip Dana (deposit customer sebelum ada invoice, mis. COD bayar di muka)
            'selected_invoices' => ['nullable', 'array'],
            'selected_invoices.*' => ['integer'],
            'invoice_receipts' => ['nullable', 'array'],
            'overpayment_as_deposit' => ['nullable', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentMethod = $validated['payment_method'];
        $depositOnly = empty($validated['selected_invoices']) && (float) ($validated['deposit_amount'] ?? 0) > 0;

        if (! $depositOnly && empty($validated['selected_invoices'])) {
            throw ValidationException::withMessages([
                'selected_invoices' => 'Pilih minimal satu invoice, atau isi nominal Titip Dana bila customer belum punya tagihan.',
            ]);
        }

        // Validasi referensi untuk metode non-tunai (Transfer, Giro, Cheque)
        if (CustomerReceiptReference::requiresReference($paymentMethod) && empty(trim((string) ($validated['payment_reference'] ?? '')))) {
            $label = CustomerReceiptReference::label($paymentMethod);
            throw ValidationException::withMessages([
                'payment_reference' => "{$label} wajib diisi untuk metode pembayaran {$paymentMethod}.",
            ]);
        }

        // Validasi akun COA sesuai metode pembayaran
        try {
            $this->allocator->assertAccountAllowed((int) $validated['coa_id'], $paymentMethod);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'coa_id' => $e->errors()['coa_id'] ?? 'Akun Kas/Bank tidak sesuai dengan metode pembayaran yang dipilih.',
            ]);
        }

        if ($depositOnly) {
            return $this->storeDepositOnly($request, $validated);
        }

        $allowDepositOverpayment = (bool) ($request->boolean('overpayment_as_deposit'));

        // Jalankan alokasi pembayaran resmi menggunakan CustomerReceiptAllocator
        $plan = $this->allocator->plan(
            (int) $validated['customer_id'],
            $validated['invoice_receipts'],
            $paymentMethod,
            $allowDepositOverpayment,
            null,
            Auth::user()
        );

        if ($plan['overpayment'] > 0 && !ChartOfAccount::where('code', config('coa.customer_deposit'))->exists()) {
            throw ValidationException::withMessages([
                'overpayment_as_deposit' => 'Akun Deposit Pelanggan (' . config('coa.customer_deposit') . ') belum ada di Chart of Account, sehingga kelebihan bayar belum dapat dicatat.',
            ]);
        }

        // Upload berkas bukti bila ada
        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('customer-receipts/proofs', 'local');
        }

        try {
            $receipt = DB::transaction(function () use ($validated, $plan, $proofPath, $paymentMethod, $allowDepositOverpayment) {
            $totalApplied = round(array_sum($plan['applied']), 2);
            $overpayment = round((float) ($plan['overpayment'] ?? 0), 2);
            $firstInvoiceId = array_key_first($plan['applied']);

            $appliedInvoiceIds = array_map('intval', array_keys($plan['applied']));

            // Ambil dan kunci baris Invoice & AccountReceivable secara atomik untuk mencegah race condition pelunasan dobel
            $lockedInvoices = Invoice::withoutGlobalScopes()->whereIn('id', $appliedInvoiceIds)->lockForUpdate()->get()->keyBy('id');
            $lockedArs = AccountReceivable::whereIn('invoice_id', $appliedInvoiceIds)->lockForUpdate()->get()->keyBy('invoice_id');

            foreach ($plan['applied'] as $invId => $allocAmount) {
                $inv = $lockedInvoices->get($invId);
                $ar = $lockedArs->get($invId);
                $remaining = (float) ($ar?->remaining ?? $inv?->total ?? 0);
                $invNum = $inv?->invoice_number ?? "#{$invId}";

                if ($remaining <= 0.01) {
                    throw ValidationException::withMessages([
                        'invoice_receipts' => "Invoice {$invNum} sudah lunas atau tidak memiliki sisa piutang. Transaksi kasir lain mungkin telah memprosesnya.",
                    ]);
                }

                if ($allocAmount > ($remaining + 1.00)) {
                    throw ValidationException::withMessages([
                        'invoice_receipts' => "Nominal alokasi untuk invoice {$invNum} melebihi sisa piutang terkini (tersisa: " . MoneyHelper::rupiah($remaining) . "). Segera muat ulang halaman kasir.",
                    ]);
                }
            }

            // Ambil konteks mata uang dari invoice pertama (bila ada)
            $firstInvoice = $lockedInvoices->get($firstInvoiceId);
            $currencyId = is_numeric($firstInvoice?->currency_id ?? null) ? (int) $firstInvoice->currency_id : null;
            $exchangeRate = (float) ($firstInvoice?->exchange_rate ?? 1.0);
            $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;

            // 1. Buat CustomerReceipt Header
            $receipt = CustomerReceipt::create([
                'customer_id' => (int) $validated['customer_id'],
                'cabang_id' => $plan['cabang_id'] ?? Auth::user()?->cabang_id,
                'invoice_id' => $firstInvoiceId,
                'selected_invoices' => $appliedInvoiceIds,
                'invoice_receipts' => $plan['applied'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $paymentMethod,
                'payment_reference' => $validated['payment_reference'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'proof_path' => $proofPath,
                'coa_id' => (int) $validated['coa_id'],
                'total_payment' => $totalApplied,
                'total_payment_idr' => $totalApplied,
                'overpayment_amount' => $overpayment,
                'notes' => $validated['notes'] ?? null,
                'status' => 'Draft',
                'created_by' => Auth::id(),
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
            ]);

            // Peringatkan bila ada referensi ganda
            $this->referenceService->warnIfDuplicate($receipt);

            // Tandai agar observer tidak menduplikasi pemotongan AR
            CustomerReceiptObserver::markArUpdatedInCreate($receipt->id);

            // 2. Buat CustomerReceiptItem dan perbarui Account Receivable
            $allPaid = true;
            $anyPartial = false;

            foreach ($plan['applied'] as $invId => $appliedAmount) {
                $inv = Invoice::withoutGlobalScopes()->find($invId);
                $itemCurrencyId = is_numeric($inv?->currency_id ?? null) ? (int) $inv->currency_id : $currencyId;
                $itemExchangeRate = (float) ($inv?->exchange_rate ?? $exchangeRate);
                $itemExchangeRate = $itemExchangeRate > 0 ? $itemExchangeRate : 1.0;

                CustomerReceiptItem::create([
                    'customer_receipt_id' => $receipt->id,
                    'invoice_id' => $invId,
                    'currency_id' => $itemCurrencyId,
                    'exchange_rate' => $itemExchangeRate,
                    'method' => $paymentMethod,
                    'amount' => $appliedAmount,
                    'amount_idr' => $appliedAmount,
                    'coa_id' => $receipt->coa_id,
                    'payment_date' => $receipt->payment_date,
                    'selected_invoices' => [$invId],
                ]);

                // Update saldo Piutang (AccountReceivable)
                $ar = AccountReceivable::where('invoice_id', $invId)->first();
                if ($ar) {
                    $newPaid = round((float) $ar->paid + $appliedAmount, 2);
                    $newRemaining = round(max(0, (float) $ar->remaining - $appliedAmount), 2);
                    $arRate = (float) ($ar->exchange_rate ?? $itemExchangeRate);
                    $arRate = $arRate > 0 ? $arRate : 1.0;

                    $ar->update([
                        'paid' => $newPaid,
                        'remaining' => $newRemaining,
                        'paid_original' => round($newPaid / $arRate, 4),
                        'remaining_original' => round($newRemaining / $arRate, 4),
                        'status' => $newRemaining <= 1.00 ? PaymentStatus::PAID->value : ($ar->status ?? PaymentStatus::UNPAID->value),
                    ]);

                    if ($newRemaining <= 1.00) {
                        $inv?->update(['status' => Invoice::STATUS_PAID]);
                        if ($ar->ageingSchedule) {
                            $ar->ageingSchedule->delete();
                        }
                    } else {
                        $allPaid = false;
                        $anyPartial = true;
                        $inv?->update(['status' => Invoice::STATUS_PARTIALLY_PAID]);
                    }
                }
            }

            // Hitung ulang total payment
            $receipt->recalculateTotalPayment();

            // 3. Set Status Akhir & Buat Jurnal Akuntansi
            $finalStatus = $allPaid ? 'Paid' : ($anyPartial ? 'Partial' : 'Paid');
            $receipt->update(['status' => $finalStatus]);

            $this->ledgerService->postCustomerReceipt($receipt->fresh());

            // 4. Catat Kelebihan Bayar sebagai Deposit bila opsi aktif
            if ($overpayment > 0 && $allowDepositOverpayment) {
                $deposit = Deposit::create([
                    'deposit_number' => app(DepositNumberGenerator::class)->generate(),
                    'from_model_type' => Customer::class,
                    'from_model_id' => $receipt->customer_id,
                    'amount' => $overpayment,
                    'used_amount' => 0,
                    'remaining_amount' => $overpayment,
                    'coa_id' => $receipt->coa_id,
                    'payment_coa_id' => $receipt->coa_id,
                    'note' => 'Kelebihan bayar dari Penerimaan Customer #' . $receipt->id,
                    'status' => 'active',
                    'created_by' => Auth::id(),
                ]);

                $this->ledgerService->postDeposit($deposit, $receipt->cabang_id ? (int) $receipt->cabang_id : null);

                $receipt->update(['deposit_id' => $deposit->id]);
            }

            return $receipt;
        });
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memproses penerimaan: ' . $e->getMessage());
        }

        $msg = "Penerimaan Customer KW-" . str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT) . " senilai " . MoneyHelper::rupiah($receipt->total_payment) . " berhasil diproses.";
        if ($receipt->deposit_id && $receipt->deposit) {
            $msg .= " Kelebihan " . MoneyHelper::rupiah($receipt->overpayment_amount) . " dicatat sebagai Deposit Customer ({$receipt->deposit->deposit_number}).";
        }

        return redirect()->route('penjualan.receipts.show', $receipt->id)->with('success', $msg);
    }

    /**
     * Titip Dana: uang masuk dari customer yang belum punya tagihan (mis. COD membayar sebelum barang dikirim).
     * Dicatat sebagai Deposit Customer — Dr Kas/Bank / Cr Deposit Pelanggan — dan menambah saldo titip yang dipakai
     * verifikasi kasir untuk SO COD serta pelunasan invoice berikutnya (metode Deposit Pelanggan).
     */
    private function storeDepositOnly(Request $request, array $validated): RedirectResponse
    {
        if ($validated['payment_method'] === 'Deposit') {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode "Deposit Pelanggan" tidak dapat dipakai untuk menambah saldo deposit. Pilih Tunai, Transfer, Giro, atau Cek.',
            ]);
        }

        if (! ChartOfAccount::where('code', config('coa.customer_deposit'))->exists()) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'Akun Deposit Pelanggan (' . config('coa.customer_deposit') . ') belum ada di Chart of Account, sehingga titip dana belum dapat dicatat.',
            ]);
        }

        $amount = round((float) $validated['deposit_amount'], 2);
        $customer = Customer::findOrFail((int) $validated['customer_id']);
        $reference = trim((string) ($validated['payment_reference'] ?? ''));
        $note = 'Titip dana customer (' . $validated['payment_method'] . ($reference !== '' ? ' ' . $reference : '') . ')'
            . (! empty($validated['notes']) ? ' — ' . $validated['notes'] : '');

        $deposit = DB::transaction(function () use ($validated, $amount, $customer, $note) {
            $deposit = Deposit::create([
                'deposit_number' => app(DepositNumberGenerator::class)->generate(),
                'from_model_type' => Customer::class,
                'from_model_id' => $customer->id,
                'amount' => $amount,
                'used_amount' => 0,
                'remaining_amount' => $amount,
                'coa_id' => (int) $validated['coa_id'],
                'payment_coa_id' => (int) $validated['coa_id'],
                'note' => mb_substr($note, 0, 255),
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            $this->ledgerService->postDeposit($deposit, Auth::user()?->cabang_id ? (int) Auth::user()->cabang_id : null);

            return $deposit;
        });

        $balance = (float) Deposit::where('from_model_type', Customer::class)->where('from_model_id', $customer->id)
            ->where('status', 'active')->sum('remaining_amount');

        return redirect()->route('penjualan.receipts.index')->with('success',
            "Titip dana {$customer->name} sebesar " . MoneyHelper::rupiah($amount) . " tercatat ({$deposit->deposit_number}). "
            . 'Saldo titip sekarang ' . MoneyHelper::rupiah($balance) . '.');
    }

    /**
     * Menampilkan detail Kwitansi Penerimaan Customer.
     */
    public function show(CustomerReceipt $customerReceipt): View
    {
        $customerReceipt->loadMissing([
            'customer',
            'cabang',
            'coa',
            'createdBy',
            'deposit',
            'customerReceiptItem.invoice.accountReceivable',
            'journalEntries',
        ]);

        $user = Auth::user();
        $isSuperAdmin = $user && (
            (method_exists($user, 'canManageAllCabang') && $user->canManageAllCabang())
            || (method_exists($user, 'hasRole') && $user->hasRole(['Super Admin', 'Finance Manager', 'Director']))
        );

        $doc = $this->docBuilder->receipt($customerReceipt);
        $blocker = $this->cancellation->blocker($customerReceipt, $user);
        $canCancel = $blocker === null || ($isSuperAdmin && $this->cancellation->isSuperAdminCancellable($customerReceipt));
        $reversibleJournals = $this->cancellation->reversibleJournals($customerReceipt);

        return view('penjualan.receipts.show', compact('customerReceipt', 'doc', 'blocker', 'canCancel', 'reversibleJournals'));
    }

    /**
     * Cetak dokumen Kwitansi resmi format PDF.
     */
    public function pdf(CustomerReceipt $customerReceipt): Response
    {
        $customerReceipt->loadMissing([
            'customer',
            'cabang',
            'customerReceiptItem.invoice',
            'createdBy',
        ]);

        $doc = $this->docBuilder->receipt($customerReceipt);

        $pdf = Pdf::loadView('pdf.kwitansi', [
            'receipt' => $customerReceipt,
            'doc' => $doc,
        ])->setPaper('a5', 'landscape');

        $filename = 'Kwitansi_KW-' . str_pad((string) $customerReceipt->id, 6, '0', STR_PAD_LEFT) . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Membatalkan Penerimaan Customer secara resmi (Reversal Jurnal & Pengembalian Piutang).
     */
    public function cancel(Request $request, CustomerReceipt $customerReceipt): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.min' => 'Alasan pembatalan wajib diisi minimal 10 karakter.',
        ]);

        try {
            $user = Auth::user();
            $isSuperAdmin = $user && (
                (method_exists($user, 'canManageAllCabang') && $user->canManageAllCabang())
                || (method_exists($user, 'hasRole') && $user->hasRole(['Super Admin', 'Finance Manager', 'Director']))
            );

            $this->cancellation->cancel($customerReceipt, (string) $request->input('reason'), $user, force: $isSuperAdmin);
            return redirect()->route('penjualan.receipts.show', $customerReceipt->id)
                ->with('success', 'Penerimaan Customer berhasil dibatalkan secara resmi. Jurnal pembalik telah diposting, mutasi deposit telah disesuaikan, dan piutang tagihan telah dikembalikan.');
        } catch (ValidationException $e) {
            return redirect()->route('penjualan.receipts.show', $customerReceipt->id)
                ->withErrors($e->errors());
        } catch (Throwable $e) {
            return redirect()->route('penjualan.receipts.show', $customerReceipt->id)
                ->with('error', 'Gagal membatalkan penerimaan: ' . $e->getMessage());
        }
    }
}
