<?php

namespace App\Http\Controllers\Keuangan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\ProcessDepreciationRequest;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\Cabang;
use App\Services\AssetDepreciationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AssetDepreciationController extends Controller
{
    protected AssetDepreciationService $depreciationService;

    public function __construct(AssetDepreciationService $depreciationService)
    {
        $this->depreciationService = $depreciationService;
    }

    /**
     * Tampilkan Pusat Manajemen Depresiasi Bulanan & Riwayat Entri.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $selectedMonth = (int) ($request->query('period_month') ?: now()->month);
        $selectedYear = (int) ($request->query('period_year') ?: now()->year);
        $cabangId = $request->query('cabang_id');
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $periodDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->endOfMonth();

        // 1. Hitung 4 KPI Utama
        $monthQuery = AssetDepreciation::where('period_month', $selectedMonth)
            ->where('period_year', $selectedYear);

        if (!$canManageAll && $user?->cabang_id) {
            $monthQuery->whereHas('asset', fn($q) => $q->where('cabang_id', $user->cabang_id));
        } elseif ($cabangId) {
            $monthQuery->whereHas('asset', fn($q) => $q->where('cabang_id', $cabangId));
        }

        $monthDepreciationTotal = (float) (clone $monthQuery)->where('status', 'recorded')->sum('amount');
        $totalRecordedUnits = (clone $monthQuery)->where('status', 'recorded')->count();

        // Total akumulasi seluruh aset aktif
        $activeAssetQuery = Asset::where('status', 'active');
        if (!$canManageAll && $user?->cabang_id) {
            $activeAssetQuery->where('cabang_id', $user->cabang_id);
        } elseif ($cabangId) {
            $activeAssetQuery->where('cabang_id', $cabangId);
        }
        $totalAccumulatedAll = (float) (clone $activeAssetQuery)->sum('accumulated_depreciation');

        // Aset aktif yang siap disusutkan untuk bulan ini (belum disusutkan)
        $pendingAssetsQuery = (clone $activeAssetQuery)
            ->where('usage_date', '<=', $periodDate)
            ->whereDoesntHave('depreciationEntries', function ($q) use ($selectedMonth, $selectedYear) {
                $q->where('period_month', $selectedMonth)
                  ->where('period_year', $selectedYear)
                  ->where('status', 'recorded');
            });

        $pendingAssetsCount = (clone $pendingAssetsQuery)->count();
        $pendingAmountEstimate = (float) (clone $pendingAssetsQuery)->sum('monthly_depreciation');

        $kpis = [
            'month_depreciation_total' => $monthDepreciationTotal,
            'total_recorded_units' => $totalRecordedUnits,
            'total_accumulated_all' => $totalAccumulatedAll,
            'pending_assets_count' => $pendingAssetsCount,
            'pending_amount_estimate' => $pendingAmountEstimate,
        ];

        // 2. Query Riwayat Depresiasi
        $historyQuery = AssetDepreciation::with([
            'asset.cabang',
            'asset.assetCoa',
            'asset.depreciationExpenseCoa',
            'asset.accumulatedDepreciationCoa',
            'journalEntry',
        ]);

        if (!$canManageAll && $user?->cabang_id) {
            $historyQuery->whereHas('asset', fn($q) => $q->where('cabang_id', $user->cabang_id));
        } elseif ($cabangId) {
            $historyQuery->whereHas('asset', fn($q) => $q->where('cabang_id', $cabangId));
        }

        if ($request->has('period_month') && $request->query('period_month') !== '') {
            $historyQuery->where('period_month', $selectedMonth);
        }

        if ($request->has('period_year') && $request->query('period_year') !== '') {
            $historyQuery->where('period_year', $selectedYear);
        }

        if ($status && $status !== 'all') {
            $historyQuery->where('status', $status);
        }

        if ($search !== '') {
            $historyQuery->whereHas('asset', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $depreciations = $historyQuery->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $cabangs = $canManageAll ? Cabang::orderBy('nama')->get() : Cabang::where('id', $user?->cabang_id)->get();

        return view('keuangan.asset.depreciation.index', compact(
            'depreciations',
            'kpis',
            'selectedMonth',
            'selectedYear',
            'cabangs',
            'canManageAll'
        ));
    }

    /**
     * Jalankan eksekusi penyusutan massal untuk seluruh aset aktif pada periode tertentu.
     */
    public function processMonthly(ProcessDepreciationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $date = Carbon::parse($validated['depreciation_date']);
        $cabangId = $validated['cabang_id'] ?? null;

        if (!$canManageAll && $user?->cabang_id) {
            $cabangId = $user->cabang_id;
        }

        try {
            $query = Asset::where('status', 'active')
                ->where('usage_date', '<=', $date);

            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $assets = $query->get();

            if ($assets->isEmpty()) {
                return back()->with('error', 'Tidak ada aset tetap aktif yang memenuhi syarat untuk disusutkan pada periode tersebut.');
            }

            $successCount = 0;
            $failedCount = 0;
            $totalAmount = 0;
            $errors = [];

            foreach ($assets as $asset) {
                try {
                    $entry = $this->depreciationService->generateMonthlyDepreciation($asset, $date);
                    $successCount++;
                    $totalAmount += (float) $entry->amount;
                } catch (Throwable $e) {
                    $failedCount++;
                    $errors[] = "{$asset->code} ({$asset->name}): {$e->getMessage()}";
                }
            }

            $formattedTotal = MoneyHelper::rupiah($totalAmount);
            $monthName = $date->translatedFormat('F Y');

            if ($successCount > 0) {
                $message = "Berhasil memproses penyusutan untuk {$successCount} aset pada periode {$monthName} dengan total nominal {$formattedTotal}.";
                if ($failedCount > 0) {
                    $message .= " Namun {$failedCount} aset dilewati (sudah tercatat atau mencapai nilai sisa).";
                }

                return redirect()->route('keuangan.asset.depreciation.index', [
                    'period_month' => $date->month,
                    'period_year' => $date->year,
                ])->with('success', $message);
            }

            return back()->with('error', "Gagal memproses penyusutan. Seluruh aset ({$failedCount}) dilewati atau sudah pernah disusutkan pada periode {$monthName}.");
        } catch (Throwable $e) {
            Log::error('AssetDepreciationController@processMonthly failed', [
                'error' => $e->getMessage(),
                'date' => $date->toDateString(),
            ]);

            return back()->with('error', 'Terjadi kesalahan sistem saat memproses penyusutan: ' . $e->getMessage());
        }
    }

    /**
     * Jalankan eksekusi penyusutan manual untuk satu aset individual.
     */
    public function processSingle(Asset $asset, Request $request): RedirectResponse
    {
        $year = (int) ($request->input('period_year') ?: now()->year);
        $month = (int) ($request->input('period_month') ?: now()->month);
        $date = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        try {
            $entry = $this->depreciationService->generateMonthlyDepreciation($asset, $date);
            $amountFormatted = MoneyHelper::rupiah($entry->amount);
            $monthName = $date->translatedFormat('F Y');

            return back()->with('success', "Penyusutan aset '{$asset->name}' untuk periode {$monthName} sebesar {$amountFormatted} berhasil dicatat dan diposting ke Buku Besar.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal memproses penyusutan aset: {$e->getMessage()}");
        }
    }

    /**
     * Batalkan (Reverse) entri penyusutan dan hapus jurnal terkait.
     */
    public function reverse(AssetDepreciation $depreciation): RedirectResponse
    {
        if ($depreciation->status === 'reversed') {
            return back()->with('error', 'Entri penyusutan ini sudah pernah dibatalkan sebelumnya.');
        }

        try {
            $assetName = $depreciation->asset->name ?? 'Aset';
            $period = Carbon::createFromDate($depreciation->period_year, $depreciation->period_month, 1)->translatedFormat('F Y');

            $this->depreciationService->reverseDepreciation($depreciation);

            return back()->with('success', "Penyusutan aset '{$assetName}' periode {$period} berhasil dibatalkan. Jurnal di Buku Besar telah dibalik dan nilai buku aset telah dikembalikan.");
        } catch (Throwable $e) {
            Log::error('AssetDepreciationController@reverse failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Gagal membatalkan penyusutan: ' . $e->getMessage());
        }
    }

    /**
     * Cetak Laporan Rekapitulasi Depresiasi Bulanan (PDF A4 Landscape).
     */
    public function pdf(Request $request): Response
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $month = (int) ($request->query('period_month') ?: now()->month);
        $year = (int) ($request->query('period_year') ?: now()->year);
        $cabangId = $request->query('cabang_id');

        $query = AssetDepreciation::with([
            'asset.cabang',
            'asset.assetCoa',
            'asset.depreciationExpenseCoa',
            'asset.accumulatedDepreciationCoa',
        ])
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->where('status', 'recorded');

        if (!$canManageAll && $user?->cabang_id) {
            $query->whereHas('asset', fn($q) => $q->where('cabang_id', $user->cabang_id));
        } elseif ($cabangId) {
            $query->whereHas('asset', fn($q) => $q->where('cabang_id', $cabangId));
        }

        $items = $query->orderBy('id', 'asc')->get();
        $cabang = $cabangId ? Cabang::find($cabangId) : null;
        $periodLabel = Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y');

        $pdf = Pdf::loadView('keuangan.asset.depreciation.pdf', compact('items', 'month', 'year', 'periodLabel', 'cabang'))
            ->setPaper('a4', 'landscape');

        $filename = "Rekap_Depresiasi_{$year}_{$month}.pdf";
        return $pdf->stream($filename);
    }
}
