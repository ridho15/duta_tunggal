<?php

namespace App\Http\Controllers\Keuangan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\CashBankAccount;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransactionDetail;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\CashBankService;
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

class CashBankController extends Controller
{
    protected CashBankService $cashBankService;

    public function __construct(CashBankService $cashBankService)
    {
        $this->cashBankService = $cashBankService;
    }

    /**
     * Tampilkan daftar transaksi Kas & Bank dan monitoring saldo rekening.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('q', ''));
        $accountId = $request->query('account_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        // 1. Ambil semua akun Kas & Bank dengan kalkulasi saldo real-time (Single Query Group By)
        $journalTotals = JournalEntry::select('coa_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->groupBy('coa_id')
            ->get()
            ->keyBy('coa_id');

        $cashBankAccounts = CashBankAccount::with('coa')
            ->whereHas('coa', function ($q) {
                $q->where('is_active', true);
            })
            ->get()
            ->map(function ($acc) use ($journalTotals) {
                $coa = $acc->coa;
                $jEntry = $coa ? $journalTotals->get($coa->id) : null;
                $debit = $jEntry ? (float) $jEntry->total_debit : 0.0;
                $credit = $jEntry ? (float) $jEntry->total_credit : 0.0;
                $acc->current_balance = (float) ($coa?->opening_balance ?? 0) + $debit - $credit;
                return $acc;
            });

        // Rekapitulasi KPI Saldo Likuiditas
        $totalSaldoKas = $cashBankAccounts->filter(fn($a) => str_starts_with($a->coa?->code ?? '', '1111'))->sum('current_balance');
        $totalSaldoBank = $cashBankAccounts->filter(fn($a) => str_starts_with($a->coa?->code ?? '', '1112'))->sum('current_balance');
        $totalLikuiditas = $totalSaldoKas + $totalSaldoBank;

        // Mutasi Bulan Berjalan (In & Out)
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $monthTransactionsQuery = CashBankTransaction::whereBetween('date', [$startOfMonth, $endOfMonth]);
        if (!$canManageAll && $user?->cabang_id) {
            $monthTransactionsQuery->where('cabang_id', $user->cabang_id);
        }
        $totalMasukBulanIni = (clone $monthTransactionsQuery)->whereIn('type', ['cash_in', 'bank_in'])->sum('amount');
        $totalKeluarBulanIni = (clone $monthTransactionsQuery)->whereIn('type', ['cash_out', 'bank_out'])->sum('amount');

        // 2. Query Transaksi / Transfer berdasarkan Tab
        $baseTrxQuery = CashBankTransaction::with(['accountCoa', 'offsetCoa', 'cabang', 'transactionDetails.chartOfAccount', 'journalEntries']);
        $baseTrfQuery = CashBankTransfer::with(['fromCoa', 'toCoa', 'otherCostsCoa', 'cabang', 'journalEntries']);

        if (!$canManageAll && $user?->cabang_id) {
            $baseTrxQuery->where('cabang_id', $user->cabang_id);
            $baseTrfQuery->where('cabang_id', $user->cabang_id);
        }

        // Live counter per tab
        $counts = [
            'semua' => (clone $baseTrxQuery)->count(),
            'masuk' => (clone $baseTrxQuery)->whereIn('type', ['cash_in', 'bank_in'])->count(),
            'keluar' => (clone $baseTrxQuery)->whereIn('type', ['cash_out', 'bank_out'])->count(),
            'transfer' => (clone $baseTrfQuery)->count(),
        ];

        $transactions = null;
        $transfers = null;

        if ($tab === 'transfer') {
            $trfQuery = clone $baseTrfQuery;

            if ($search) {
                $trfQuery->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('fromCoa', fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                      ->orWhereHas('toCoa', fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            }

            if ($startDate && $endDate) {
                $trfQuery->whereBetween('date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $trfQuery->where('date', '>=', $startDate);
            } elseif ($endDate) {
                $trfQuery->where('date', '<=', $endDate);
            }

            if ($accountId) {
                $selectedAcc = CashBankAccount::find($accountId);
                if ($selectedAcc?->coa_id) {
                    $trfQuery->where(function ($q) use ($selectedAcc) {
                        $q->where('from_coa_id', $selectedAcc->coa_id)
                          ->orWhere('to_coa_id', $selectedAcc->coa_id);
                    });
                }
            }

            $transfers = $trfQuery->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();
        } else {
            $trxQuery = clone $baseTrxQuery;

            if ($tab === 'masuk') {
                $trxQuery->whereIn('type', ['cash_in', 'bank_in']);
            } elseif ($tab === 'keluar') {
                $trxQuery->whereIn('type', ['cash_out', 'bank_out']);
            }

            if ($search) {
                $trxQuery->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                      ->orWhere('counterparty', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('accountCoa', fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                      ->orWhereHas('offsetCoa', fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            }

            if ($startDate && $endDate) {
                $trxQuery->whereBetween('date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $trxQuery->where('date', '>=', $startDate);
            } elseif ($endDate) {
                $trxQuery->where('date', '<=', $endDate);
            }

            if ($accountId) {
                $selectedAcc = CashBankAccount::find($accountId);
                if ($selectedAcc?->coa_id) {
                    $trxQuery->where('account_coa_id', $selectedAcc->coa_id);
                }
            }

            $transactions = $trxQuery->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();
        }

        return view('keuangan.cashbank.index', compact(
            'tab',
            'search',
            'accountId',
            'startDate',
            'endDate',
            'cashBankAccounts',
            'totalSaldoKas',
            'totalSaldoBank',
            'totalLikuiditas',
            'totalMasukBulanIni',
            'totalKeluarBulanIni',
            'counts',
            'transactions',
            'transfers'
        ));
    }

    /**
     * Tampilkan form pembuatan transaksi Kas/Bank (Masuk / Keluar).
     */
    public function create(Request $request): View
    {
        $defaultType = $request->query('type', 'cash_out');
        $suggestedNumber = $this->cashBankService->generateNumber('CB');

        // Akun Kas & Bank (1111* dan 1112*)
        $cashBankCoas = ChartOfAccount::where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '1111%')
                  ->orWhere('code', 'like', '1112%');
            })
            ->orderBy('code')
            ->get();

        // Akun Lawan (seluruh akun non-kas/bank aktif)
        $offsetCoas = ChartOfAccount::where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'not like', '1111%')
                  ->where('code', 'not like', '1112%');
            })
            ->orderBy('code')
            ->get();

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();

        return view('keuangan.cashbank.create', compact(
            'defaultType',
            'suggestedNumber',
            'cashBankCoas',
            'offsetCoas',
            'cabangs'
        ));
    }

    /**
     * Simpan transaksi Kas/Bank baru dan posting jurnal ganda otomatis.
     */
    public function store(Request $request): RedirectResponse
    {
        $hasDetails = is_array($request->input('details')) && count($request->input('details')) > 0;

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:50', 'unique:cash_bank_transactions,number'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:cash_in,cash_out,bank_in,bank_out'],
            'account_coa_id' => ['required', 'exists:chart_of_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'offset_coa_id' => [$hasDetails ? 'nullable' : 'required', 'exists:chart_of_accounts,id', 'different:account_coa_id'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'details' => ['nullable', 'array'],
            'details.*.chart_of_account_id' => ['required_with:details', 'exists:chart_of_accounts,id'],
            'details.*.amount' => ['required_with:details', 'numeric'],
            'details.*.description' => ['nullable', 'string', 'max:255'],
        ], [
            'number.required' => 'Nomor bukti transaksi wajib diisi.',
            'number.unique' => 'Nomor bukti transaksi sudah pernah digunakan.',
            'date.required' => 'Tanggal transaksi wajib diisi.',
            'type.required' => 'Tipe transaksi wajib dipilih.',
            'account_coa_id.required' => 'Akun Kas/Bank wajib dipilih.',
            'amount.required' => 'Jumlah transaksi wajib diisi.',
            'amount.min' => 'Jumlah transaksi minimal Rp 0,01.',
            'offset_coa_id.required' => 'Akun lawan / pos rincian wajib dipilih jika tidak menggunakan rincian akun.',
            'offset_coa_id.different' => 'Akun lawan tidak boleh sama dengan akun Kas/Bank.',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('cashbank', 'public');
        }

        try {
            $transaction = DB::transaction(function () use ($validated, $attachmentPath, $request) {
                $cashBankAccountId = CashBankAccount::where('coa_id', $validated['account_coa_id'])->value('id');

                $details = $request->input('details', []);
                $offsetCoaId = $validated['offset_coa_id'] ?? null;

                if (!empty($details)) {
                    $totalDetails = collect($details)->sum(fn($d) => (float) ($d['amount'] ?? 0));
                    if (abs($totalDetails - (float) $validated['amount']) > 0.01) {
                        throw new \Exception('Total nominal rincian (Rp ' . number_format($totalDetails, 0, ',', '.') . ') tidak sama dengan jumlah transaksi (Rp ' . number_format($validated['amount'], 0, ',', '.') . ').');
                    }
                    if (!$offsetCoaId && isset($details[0]['chart_of_account_id'])) {
                        $offsetCoaId = $details[0]['chart_of_account_id'];
                    }
                }

                $trx = CashBankTransaction::create([
                    'number' => $validated['number'],
                    'date' => $validated['date'],
                    'type' => $validated['type'],
                    'cash_bank_account_id' => $cashBankAccountId,
                    'account_coa_id' => $validated['account_coa_id'],
                    'offset_coa_id' => $offsetCoaId,
                    'amount' => $validated['amount'],
                    'counterparty' => $validated['counterparty'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'cabang_id' => $validated['cabang_id'] ?? Auth::user()?->cabang_id,
                ]);

                if (!empty($details)) {
                    foreach ($details as $detail) {
                        if (!empty($detail['chart_of_account_id']) && (float) ($detail['amount'] ?? 0) != 0) {
                            CashBankTransactionDetail::create([
                                'cash_bank_transaction_id' => $trx->id,
                                'chart_of_account_id' => $detail['chart_of_account_id'],
                                'amount' => $detail['amount'],
                                'description' => $detail['description'] ?? null,
                            ]);
                        }
                    }
                }

                // Auto Posting Jurnal Akuntansi ganda
                $this->cashBankService->postTransaction($trx);

                return $trx;
            });

            return redirect()->route('keuangan.cashbank.show', $transaction)
                ->with('success', "Transaksi {$transaction->number} berhasil disimpan dan jurnal akuntansi telah dibukukan.");
        } catch (Throwable $e) {
            if ($attachmentPath && Storage::disk('public')->exists($attachmentPath)) {
                Storage::disk('public')->delete($attachmentPath);
            }

            return back()->withInput()->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail transaksi Kas/Bank beserta status jurnal.
     */
    public function show(CashBankTransaction $transaction): View
    {
        $transaction->load([
            'accountCoa',
            'offsetCoa',
            'cabang',
            'transactionDetails.chartOfAccount',
            'journalEntries.coa'
        ]);

        return view('keuangan.cashbank.show', compact('transaction'));
    }

    /**
     * Batalkan / Hapus transaksi Kas/Bank beserta jurnal akuntansi terkait.
     */
    public function destroy(CashBankTransaction $transaction): RedirectResponse
    {
        try {
            DB::transaction(function () use ($transaction) {
                // Hapus jurnal terkait
                $transaction->journalEntries()->delete();

                // Hapus detail rincian
                $transaction->transactionDetails()->delete();

                // Hapus file lampiran jika ada
                if ($transaction->attachment_path && Storage::disk('public')->exists($transaction->attachment_path)) {
                    Storage::disk('public')->delete($transaction->attachment_path);
                }

                $transaction->delete();
            });

            return redirect()->route('keuangan.cashbank.index')
                ->with('success', "Transaksi {$transaction->number} dan jurnal akuntansinya berhasil dihapus.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Cetak PDF Bukti Kas Masuk (BKM) atau Bukti Kas Keluar (BKK).
     */
    public function pdf(CashBankTransaction $transaction): Response
    {
        $transaction->load([
            'accountCoa',
            'offsetCoa',
            'cabang',
            'transactionDetails.chartOfAccount'
        ]);

        $pdf = Pdf::loadView('pdf.cash-bank-voucher', compact('transaction'))
            ->setPaper('a4', 'portrait');

        $filename = "Voucher_{$transaction->type}_{$transaction->number}.pdf";
        return $pdf->stream($filename);
    }

    /**
     * Tampilkan form pembuatan transfer antar rekening Kas & Bank.
     */
    public function createTransfer(): View
    {
        $suggestedNumber = $this->cashBankService->generateTransferNumber();

        // Akun Kas & Bank
        $cashBankCoas = ChartOfAccount::where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '1111%')
                  ->orWhere('code', 'like', '1112%');
            })
            ->orderBy('code')
            ->get();

        // Akun Biaya Admin Bank (default ke akun beban admin / biaya lainnya)
        $expenseCoas = ChartOfAccount::where('is_active', true)
            ->where('type', 'Expense')
            ->orderBy('code')
            ->get();

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();

        return view('keuangan.cashbank.transfer.create', compact(
            'suggestedNumber',
            'cashBankCoas',
            'expenseCoas',
            'cabangs'
        ));
    }

    /**
     * Simpan transfer antar rekening Kas & Bank dan posting jurnal ganda.
     */
    public function storeTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:50', 'unique:cash_bank_transfers,number'],
            'date' => ['required', 'date'],
            'from_coa_id' => ['required', 'exists:chart_of_accounts,id'],
            'to_coa_id' => ['required', 'exists:chart_of_accounts,id', 'different:from_coa_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'other_costs_coa_id' => ['nullable', 'required_if:other_costs,>0', 'exists:chart_of_accounts,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'number.required' => 'Nomor transfer wajib diisi.',
            'number.unique' => 'Nomor transfer sudah pernah digunakan.',
            'date.required' => 'Tanggal transfer wajib diisi.',
            'from_coa_id.required' => 'Akun sumber dana wajib dipilih.',
            'to_coa_id.required' => 'Akun tujuan transfer wajib dipilih.',
            'to_coa_id.different' => 'Akun tujuan tidak boleh sama dengan akun sumber dana.',
            'amount.required' => 'Nominal transfer wajib diisi.',
            'amount.min' => 'Nominal transfer minimal Rp 0,01.',
            'other_costs_coa_id.required_if' => 'Akun COA Biaya Admin wajib dipilih jika ada biaya admin transfer.',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('cashbank', 'public');
        }

        try {
            $transfer = DB::transaction(function () use ($validated, $attachmentPath) {
                $otherCosts = (float) ($validated['other_costs'] ?? 0);

                $trf = CashBankTransfer::create([
                    'number' => $validated['number'],
                    'date' => $validated['date'],
                    'from_coa_id' => $validated['from_coa_id'],
                    'to_coa_id' => $validated['to_coa_id'],
                    'amount' => $validated['amount'],
                    'other_costs' => $otherCosts,
                    'other_costs_coa_id' => $otherCosts > 0 ? ($validated['other_costs_coa_id'] ?? null) : null,
                    'reference' => $validated['reference'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'status' => 'draft',
                    'cabang_id' => $validated['cabang_id'] ?? Auth::user()?->cabang_id,
                ]);

                // Auto Posting Jurnal Transfer (Cr From, Dr To, Dr Biaya Admin)
                $this->cashBankService->postTransfer($trf);

                return $trf;
            });

            return redirect()->route('keuangan.cashbank.transfer.show', $transfer)
                ->with('success', "Transfer {$transfer->number} berhasil dibukukan dan jurnal kas telah diperbarui.");
        } catch (Throwable $e) {
            if ($attachmentPath && Storage::disk('public')->exists($attachmentPath)) {
                Storage::disk('public')->delete($attachmentPath);
            }

            return back()->withInput()->with('error', 'Gagal memproses transfer: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail bukti transfer antar rekening.
     */
    public function showTransfer(CashBankTransfer $transfer): View
    {
        $transfer->load([
            'fromCoa',
            'toCoa',
            'otherCostsCoa',
            'cabang',
            'journalEntries.coa'
        ]);

        return view('keuangan.cashbank.transfer.show', compact('transfer'));
    }

    /**
     * Hapus / Batalkan transfer antar rekening dan jurnalnya.
     */
    public function destroyTransfer(CashBankTransfer $transfer): RedirectResponse
    {
        try {
            DB::transaction(function () use ($transfer) {
                $transfer->journalEntries()->delete();

                if ($transfer->attachment_path && Storage::disk('public')->exists($transfer->attachment_path)) {
                    Storage::disk('public')->delete($transfer->attachment_path);
                }

                $transfer->delete();
            });

            return redirect()->route('keuangan.cashbank.index', ['tab' => 'transfer'])
                ->with('success', "Transfer {$transfer->number} dan jurnalnya berhasil dibatalkan.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membatalkan transfer: ' . $e->getMessage());
        }
    }

    /**
     * Cetak PDF Bukti Transfer Antar Rekening Kas/Bank.
     */
    public function pdfTransfer(CashBankTransfer $transfer): Response
    {
        $transfer->load([
            'fromCoa',
            'toCoa',
            'otherCostsCoa',
            'cabang'
        ]);

        $pdf = Pdf::loadView('pdf.cash-bank-transfer', compact('transfer'))
            ->setPaper('a4', 'portrait');

        $filename = "Transfer_{$transfer->number}.pdf";
        return $pdf->stream($filename);
    }
}
