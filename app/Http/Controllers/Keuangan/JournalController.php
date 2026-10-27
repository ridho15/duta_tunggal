<?php

namespace App\Http\Controllers\Keuangan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class JournalController extends Controller
{
    /**
     * Tampilkan daftar Jurnal Umum (General Journal Book) berkelompok per transaksi.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('q', ''));
        $coaId = $request->query('coa_id');
        $cabangId = $request->query('cabang_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        // 1. KPI Mutasi Periode Ini
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $monthKpiQuery = JournalEntry::whereBetween('date', [$startOfMonth, $endOfMonth]);
        if (!$canManageAll && $user?->cabang_id) {
            $monthKpiQuery->where('cabang_id', $user->cabang_id);
        }

        $totalDebitMonth = (clone $monthKpiQuery)->sum('debit');
        $totalCreditMonth = (clone $monthKpiQuery)->sum('credit');
        $totalVouchersMonth = (clone $monthKpiQuery)->distinct('reference')->count('reference');
        $balanceDifference = abs($totalDebitMonth - $totalCreditMonth);

        // 2. Query Pengelompokan Transaksi Jurnal (Grouped by Reference)
        $baseQuery = JournalEntry::whereNotNull('reference');

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        } elseif ($cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Filter berdasarkan Tab Modul Jurnal
        $tabFilters = [
            'pembelian' => ['purchase', 'purchase_invoice', 'purchase_return', 'goods_receipt'],
            'penjualan' => ['sales', 'sales_invoice', 'customer_return', 'credit_note'],
            'kasbank' => ['cashbank', 'cash_in', 'cash_out', 'bank_in', 'bank_out'],
            'transfer' => ['transfer', 'bank_transfer'],
            'pembayaran' => ['payment', 'vendor_payment', 'receipt', 'customer_receipt'],
            'persediaan' => ['inventory', 'stock_opname', 'stock_adjustment', 'stock_transfer'],
            'manual' => ['manual', 'general', 'adjustment', 'reversal'],
        ];

        // Hitung count untuk tabs
        $counts = [
            'semua' => (clone $baseQuery)->distinct('reference')->count('reference'),
            'pembelian' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['pembelian'])->distinct('reference')->count('reference'),
            'penjualan' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['penjualan'])->distinct('reference')->count('reference'),
            'kasbank' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['kasbank'])->distinct('reference')->count('reference'),
            'transfer' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['transfer'])->distinct('reference')->count('reference'),
            'pembayaran' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['pembayaran'])->distinct('reference')->count('reference'),
            'persediaan' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['persediaan'])->distinct('reference')->count('reference'),
            'manual' => (clone $baseQuery)->whereIn('journal_type', $tabFilters['manual'])->distinct('reference')->count('reference'),
        ];

        $filterQuery = clone $baseQuery;

        if ($tab !== 'semua' && isset($tabFilters[$tab])) {
            $filterQuery->whereIn('journal_type', $tabFilters[$tab]);
        }

        if ($startDate && $endDate) {
            $filterQuery->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $filterQuery->where('date', '>=', $startDate);
        } elseif ($endDate) {
            $filterQuery->where('date', '<=', $endDate);
        }

        if ($coaId) {
            $filterQuery->where('coa_id', $coaId);
        }

        if ($search) {
            $filterQuery->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('coa', fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        // Grouping by Reference
        $groupedVouchers = $filterQuery->select(
            'reference',
            DB::raw('MAX(date) as date'),
            DB::raw('MAX(journal_type) as journal_type'),
            DB::raw('MAX(cabang_id) as cabang_id'),
            DB::raw('MAX(description) as description'),
            DB::raw('MAX(source_type) as source_type'),
            DB::raw('MAX(source_id) as source_id'),
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit'),
            DB::raw('COUNT(id) as lines_count')
        )
        ->groupBy('reference')
        ->orderBy('date', 'desc')
        ->orderBy(DB::raw('MAX(id)'), 'desc')
        ->paginate(15)
        ->withQueryString();

        // Ambil baris jurnal untuk voucher yang sedang ditampilkan
        $referenceList = $groupedVouchers->pluck('reference');
        $journalLines = JournalEntry::with(['coa', 'cabang', 'source'])
            ->whereIn('reference', $referenceList)
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('reference');

        // Master Akun COA untuk dropdown filter
        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();

        return view('keuangan.journal.index', compact(
            'tab',
            'search',
            'coaId',
            'cabangId',
            'startDate',
            'endDate',
            'totalDebitMonth',
            'totalCreditMonth',
            'totalVouchersMonth',
            'balanceDifference',
            'counts',
            'groupedVouchers',
            'journalLines',
            'chartOfAccounts',
            'cabangs'
        ));
    }

    /**
     * Tampilkan Buku Besar per Akun (General Ledger / Ledger Card).
     */
    public function ledger(Request $request): View
    {
        $coaId = $request->query('coa_id');
        $cabangId = $request->query('cabang_id');
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        // Ambil daftar seluruh akun COA
        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();

        // Default ke akun pertama jika belum dipilih
        if (!$coaId && $chartOfAccounts->isNotEmpty()) {
            $coaId = $chartOfAccounts->first()->id;
        }

        $selectedCoa = $coaId ? ChartOfAccount::find($coaId) : null;
        $isDebitNormal = $selectedCoa ? in_array($selectedCoa->type, ['Asset', 'Expense']) : true;

        $openingBalance = 0.0;
        $totalDebitPeriod = 0.0;
        $totalCreditPeriod = 0.0;
        $endingBalance = 0.0;
        $pageStartingBalance = 0.0;
        $ledgerEntries = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        if ($selectedCoa) {
            // Hitung Saldo Awal (Opening balance per tgl mulai)
            $baseOpening = (float) ($selectedCoa->opening_balance ?? 0);

            $priorQuery = JournalEntry::where('coa_id', $selectedCoa->id)
                ->where('date', '<', $startDate);

            if (!$canManageAll && $user?->cabang_id) {
                $priorQuery->where('cabang_id', $user->cabang_id);
            } elseif ($cabangId) {
                $priorQuery->where('cabang_id', $cabangId);
            }

            $priorDebit = (float) (clone $priorQuery)->sum('debit');
            $priorCredit = (float) (clone $priorQuery)->sum('credit');

            if ($isDebitNormal) {
                $openingBalance = $baseOpening + $priorDebit - $priorCredit;
            } else {
                $openingBalance = $baseOpening - $priorDebit + $priorCredit;
            }

            // Ambil mutasi dalam periode
            $periodQuery = JournalEntry::with(['cabang', 'source'])
                ->where('coa_id', $selectedCoa->id)
                ->whereBetween('date', [$startDate, $endDate]);

            if (!$canManageAll && $user?->cabang_id) {
                $periodQuery->where('cabang_id', $user->cabang_id);
            } elseif ($cabangId) {
                $periodQuery->where('cabang_id', $cabangId);
            }

            // Total mutasi periode keseluruhan
            $totalDebitPeriod = (float) (clone $periodQuery)->sum('debit');
            $totalCreditPeriod = (float) (clone $periodQuery)->sum('credit');

            if ($isDebitNormal) {
                $endingBalance = $openingBalance + $totalDebitPeriod - $totalCreditPeriod;
            } else {
                $endingBalance = $openingBalance - $totalDebitPeriod + $totalCreditPeriod;
            }

            // Paginasi 25 baris per halaman
            $perPage = 25;
            $currentPage = (int) $request->query('page', 1);
            if ($currentPage < 1) {
                $currentPage = 1;
            }
            $pageOffset = ($currentPage - 1) * $perPage;

            // Hitung saldo bawaan untuk halaman aktif
            $priorInPeriodDebit = 0.0;
            $priorInPeriodCredit = 0.0;
            if ($pageOffset > 0) {
                $priorInPeriod = (clone $periodQuery)->orderBy('date', 'asc')->orderBy('id', 'asc')->take($pageOffset)->get();
                $priorInPeriodDebit = (float) $priorInPeriod->sum('debit');
                $priorInPeriodCredit = (float) $priorInPeriod->sum('credit');
            }

            if ($isDebitNormal) {
                $pageStartingBalance = $openingBalance + $priorInPeriodDebit - $priorInPeriodCredit;
            } else {
                $pageStartingBalance = $openingBalance - $priorInPeriodDebit + $priorInPeriodCredit;
            }

            $entries = (clone $periodQuery)->orderBy('date', 'asc')->orderBy('id', 'asc')->paginate($perPage)->withQueryString();

            // Hitung Running Balance baris demi baris pada halaman aktif
            $currentRunning = $pageStartingBalance;
            foreach ($entries as $entry) {
                $dr = (float) $entry->debit;
                $cr = (float) $entry->credit;

                if ($isDebitNormal) {
                    $currentRunning += $dr - $cr;
                } else {
                    $currentRunning += $cr - $dr;
                }
                $entry->running_balance = $currentRunning;
            }

            $ledgerEntries = $entries;
        }

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();

        return view('keuangan.journal.ledger', compact(
            'coaId',
            'cabangId',
            'startDate',
            'endDate',
            'selectedCoa',
            'chartOfAccounts',
            'isDebitNormal',
            'openingBalance',
            'pageStartingBalance',
            'totalDebitPeriod',
            'totalCreditPeriod',
            'endingBalance',
            'ledgerEntries',
            'cabangs'
        ));
    }

    /**
     * Tampilkan form pembuatan Jurnal Penyesuaian Manual (Manual Journal Entry).
     */
    public function create(): View
    {
        $dateStr = now()->format('Ymd');
        $suggestedReference = 'JV-' . $dateStr . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();

        return view('keuangan.journal.create', compact(
            'suggestedReference',
            'chartOfAccounts',
            'cabangs'
        ));
    }

    /**
     * Simpan Jurnal Penyesuaian Manual dan validasi balance Debit = Kredit.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:50', 'unique:journal_entries,reference'],
            'date' => ['required', 'date'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
            'description' => ['required', 'string', 'max:500'],
            'journal_type' => ['required', 'in:manual,adjustment,reversal'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.coa_id' => ['required', 'exists:chart_of_accounts,id'],
            'entries.*.description' => ['nullable', 'string', 'max:255'],
            'entries.*.debit' => ['nullable', 'numeric', 'min:0'],
            'entries.*.credit' => ['nullable', 'numeric', 'min:0'],
        ], [
            'reference.required' => 'Nomor referensi / voucher jurnal wajib diisi.',
            'reference.unique' => 'Nomor referensi jurnal sudah pernah digunakan.',
            'date.required' => 'Tanggal jurnal wajib diisi.',
            'description.required' => 'Keterangan umum jurnal wajib diisi.',
            'entries.min' => 'Jurnal harus memiliki minimal 2 baris akun (Debit dan Kredit).',
            'entries.*.coa_id.required' => 'Akun COA pada setiap baris jurnal wajib dipilih.',
        ]);

        // Validasi Keseimbangan Double-Entry
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($validated['entries'] as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withInput()->with('error', 'Jurnal tidak seimbang! Total Debit (Rp ' . number_format($totalDebit, 0, ',', '.') . ') harus sama persis dengan Total Kredit (Rp ' . number_format($totalCredit, 0, ',', '.') . ').');
        }

        if ($totalDebit <= 0) {
            return back()->withInput()->with('error', 'Nominal jurnal tidak boleh nol.');
        }

        try {
            DB::transaction(function () use ($validated, $totalDebit) {
                $cabangId = $validated['cabang_id'] ?? Auth::user()?->cabang_id;
                $userId = Auth::id();

                AccountingPeriod::ensureDateIsOpen($validated['date'], $cabangId);

                foreach ($validated['entries'] as $line) {
                    $debit = (float) ($line['debit'] ?? 0);
                    $credit = (float) ($line['credit'] ?? 0);

                    // Hanya simpan baris yang memiliki nilai > 0
                    if ($debit > 0 || $credit > 0) {
                        JournalEntry::create([
                            'coa_id' => $line['coa_id'],
                            'date' => $validated['date'],
                            'reference' => $validated['reference'],
                            'description' => $line['description'] ?: $validated['description'],
                            'debit' => $debit,
                            'credit' => $credit,
                            'journal_type' => $validated['journal_type'],
                            'cabang_id' => $cabangId,
                            'created_by' => $userId,
                            'source_type' => null,
                            'source_id' => null,
                        ]);
                    }
                }
            });

            return redirect()->route('keuangan.journal.show', $validated['reference'])
                ->with('success', "Voucher Jurnal {$validated['reference']} berhasil disimpan dan dibukukan ke Buku Besar.");
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Gagal membukukan jurnal: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail satu transaksi Voucher Jurnal.
     */
    public function show(string $reference): View
    {
        $lines = JournalEntry::with(['coa', 'cabang', 'source'])
            ->where('reference', $reference)
            ->orderBy('id', 'asc')
            ->get();

        if ($lines->isEmpty()) {
            abort(404, "Voucher jurnal dengan referensi {$reference} tidak ditemukan.");
        }

        $header = (object) [
            'reference' => $reference,
            'date' => $lines->first()->date,
            'journal_type' => $lines->first()->journal_type,
            'cabang' => $lines->first()->cabang,
            'description' => $lines->first()->description,
            'source_type' => $lines->first()->source_type,
            'source_id' => $lines->first()->source_id,
            'source' => $lines->first()->source,
            'total_debit' => $lines->sum('debit'),
            'total_credit' => $lines->sum('credit'),
            'is_manual' => empty($lines->first()->source_type) || in_array($lines->first()->journal_type, ['manual', 'adjustment', 'reversal']),
            'created_at' => $lines->first()->created_at,
        ];

        return view('keuangan.journal.show', compact('header', 'lines'));
    }

    /**
     * Batalkan / Hapus Voucher Jurnal Manual (jurnal otomatis sistem diproteksi).
     */
    public function destroy(string $reference): RedirectResponse
    {
        $lines = JournalEntry::where('reference', $reference)->get();

        if ($lines->isEmpty()) {
            return back()->with('error', "Voucher jurnal {$reference} tidak ditemukan.");
        }

        // Proteksi: Jurnal otomatis dari sistem tidak boleh dihapus manual
        $firstEntry = $lines->first();
        $isManual = empty($firstEntry->source_type) || in_array($firstEntry->journal_type, ['manual', 'adjustment', 'reversal']);

        if (!$isManual) {
            return back()->with('error', "Jurnal ini dibuat otomatis oleh transaksi sistem ({$firstEntry->journal_type}). Untuk menjaga integritas data, jurnal otomatis tidak dapat dihapus secara manual.");
        }

        try {
            DB::transaction(function () use ($lines) {
                foreach ($lines as $line) {
                    $line->delete();
                }
            });

            return redirect()->route('keuangan.journal.index')
                ->with('success', "Voucher Jurnal {$reference} berhasil dihapus dari Buku Besar.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menghapus jurnal: ' . $e->getMessage());
        }
    }

    /**
     * Cetak PDF Voucher Jurnal (A4 Portrait formal).
     */
    public function pdf(string $reference): Response
    {
        $lines = JournalEntry::with(['coa', 'cabang'])
            ->where('reference', $reference)
            ->orderBy('id', 'asc')
            ->get();

        if ($lines->isEmpty()) {
            abort(404);
        }

        $header = (object) [
            'reference' => $reference,
            'date' => $lines->first()->date,
            'journal_type' => $lines->first()->journal_type,
            'cabang' => $lines->first()->cabang,
            'description' => $lines->first()->description,
            'total_debit' => $lines->sum('debit'),
            'total_credit' => $lines->sum('credit'),
        ];

        $pdf = Pdf::loadView('pdf.journal-voucher', compact('header', 'lines'))
            ->setPaper('a4', 'portrait');

        $filename = "Jurnal_{$reference}.pdf";
        return $pdf->stream($filename);
    }
}
