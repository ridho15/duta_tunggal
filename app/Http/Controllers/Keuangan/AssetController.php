<?php

namespace App\Http\Controllers\Keuangan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Services\AssetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AssetController extends Controller
{
    protected AssetService $assetService;

    public function __construct(AssetService $assetService)
    {
        $this->assetService = $assetService;
    }

    /**
     * Tampilkan daftar Master Aset Tetap dan 5 KPI Utama.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $search = trim((string) $request->query('q', ''));
        $cabangId = $request->query('cabang_id');
        $status = $request->query('status');
        $assetCoaId = $request->query('asset_coa_id');
        $depreciationMethod = $request->query('depreciation_method');
        $dateFrom = $request->query('date_from');
        $dateUntil = $request->query('date_until');

        // Base Query untuk KPI & Filter
        $kpiQuery = Asset::query();
        if (!$canManageAll && $user?->cabang_id) {
            $kpiQuery->where('cabang_id', $user->cabang_id);
        } elseif ($cabangId) {
            $kpiQuery->where('cabang_id', $cabangId);
        }

        // 5 KPI Cards
        $kpis = [
            'total_units' => (clone $kpiQuery)->count(),
            'total_cost' => (float) (clone $kpiQuery)->sum('purchase_cost'),
            'total_accumulated_depreciation' => (float) (clone $kpiQuery)->sum('accumulated_depreciation'),
            'total_book_value' => (float) (clone $kpiQuery)->sum('book_value'),
            'total_active' => (clone $kpiQuery)->whereIn('status', ['active', 'posted'])->count(),
        ];

        // Query Utama untuk Tabel
        $query = Asset::with([
            'cabang',
            'assetCoa',
            'accumulatedDepreciationCoa',
            'depreciationExpenseCoa',
        ]);

        if (!$canManageAll && $user?->cabang_id) {
            $query->where('cabang_id', $user->cabang_id);
        } elseif ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', ['active', 'posted']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($assetCoaId) {
            $query->where('asset_coa_id', $assetCoaId);
        }

        if ($depreciationMethod) {
            $query->where('depreciation_method', $depreciationMethod);
        }

        if ($dateFrom) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        if ($dateUntil) {
            $query->whereDate('purchase_date', '<=', $dateUntil);
        }

        $assets = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Data untuk Dropdown Filter
        $cabangs = $canManageAll ? Cabang::orderBy('nama')->get() : Cabang::where('id', $user?->cabang_id)->get();
        $assetCoas = ChartOfAccount::where(function ($q) {
            $q->where('code', 'like', '1210%')
              ->orWhere('type', 'fixed_asset');
        })->orderBy('code')->get();

        return view('keuangan.asset.index', compact('assets', 'kpis', 'cabangs', 'assetCoas', 'canManageAll'));
    }

    /**
     * Tampilkan formulir pendaftaran aset tetap baru.
     */
    public function create(): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $cabangs = $canManageAll ? Cabang::orderBy('nama')->get() : Cabang::where('id', $user?->cabang_id)->get();

        // COA Aset Tetap (1210.xx)
        $assetCoas = ChartOfAccount::where('code', 'like', '1210%')
            ->orderBy('code')
            ->get();

        // Fallback jika tidak ada 1210: ambil semua tipe fixed_asset
        if ($assetCoas->isEmpty()) {
            $assetCoas = ChartOfAccount::where('type', 'fixed_asset')->orderBy('code')->get();
        }

        // COA Akumulasi Penyusutan (1220.xx)
        $accumulatedCoas = ChartOfAccount::where('code', 'like', '1220%')
            ->orderBy('code')
            ->get();

        // COA Beban Penyusutan (631x)
        $expenseCoas = ChartOfAccount::where('code', 'like', '631%')
            ->orderBy('code')
            ->get();

        // Akun Pendanaan / Kredit (Kas & Bank atau Hutang Usaha)
        $fundingCoas = ChartOfAccount::where(function ($q) {
            $q->where('code', 'like', '110%') // Kas & Bank
              ->orWhere('code', 'like', '2110%') // Hutang Usaha
              ->orWhere('code', 'like', '2100%');
        })->orderBy('code')->get();

        $products = Product::orderBy('name')->limit(100)->get(['id', 'sku', 'name', 'cost_price']);
        $autoCode = Asset::generateAssetCode();

        return view('keuangan.asset.create', compact(
            'cabangs',
            'assetCoas',
            'accumulatedCoas',
            'expenseCoas',
            'fundingCoas',
            'products',
            'autoCode',
            'canManageAll'
        ));
    }

    /**
     * Simpan data aset tetap baru dan posting jurnal akuisisi.
     */
    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $creditCoaId = $validated['credit_coa_id'] ?? null;
        unset($validated['credit_coa_id']);

        try {
            DB::beginTransaction();

            $asset = new Asset($validated);
            if ($creditCoaId) {
                $asset->credit_coa_id = (int) $creditCoaId;
            }

            // Hitung nilai depresiasi awal
            $amounts = $asset->depreciationAmounts();
            $asset->annual_depreciation = $amounts['annual_depreciation'];
            $asset->monthly_depreciation = $amounts['monthly_depreciation'];
            $asset->accumulated_depreciation = 0;
            $asset->book_value = $asset->purchase_cost;
            $asset->save();

            // Posting Jurnal Akuisisi ke Buku Besar jika belum terposting oleh observer
            if (!$this->assetService->hasPostedJournals($asset)) {
                $this->assetService->postAssetAcquisitionJournal($asset, $creditCoaId ? (int) $creditCoaId : null);
            }

            DB::commit();

            return redirect()->route('keuangan.asset.show', $asset)
                ->with('success', "Aset Tetap '{$asset->name}' ({$asset->code}) berhasil didaftarkan dan Jurnal Akuisisi telah diposting ke Buku Besar.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('AssetController@store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'Gagal mendaftarkan aset: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail aset, kartu nilai buku, jurnal akuntansi, dan riwayat mutasi.
     */
    public function show(Asset $asset): View
    {
        $asset->load([
            'cabang',
            'product',
            'purchaseOrder',
            'assetCoa',
            'accumulatedDepreciationCoa',
            'depreciationExpenseCoa',
            'depreciationEntries' => fn($q) => $q->orderBy('period_year', 'desc')->orderBy('period_month', 'desc'),
            'journalEntries' => fn($q) => $q->with('coa')->orderBy('date', 'desc')->orderBy('id', 'desc'),
            'transfers' => fn($q) => $q->with(['fromCabang', 'toCabang'])->orderBy('created_at', 'desc'),
            'disposals' => fn($q) => $q->orderBy('created_at', 'desc'),
        ]);

        $hasJournals = $asset->hasPostedJournals();

        // Hitung persentase terdepresiasi
        $depreciationPercentage = $asset->purchase_cost > 0
            ? min(100, round(($asset->accumulated_depreciation / $asset->purchase_cost) * 100, 1))
            : 0;

        return view('keuangan.asset.show', compact('asset', 'hasJournals', 'depreciationPercentage'));
    }

    /**
     * Formulir ubah data aset tetap.
     */
    public function edit(Asset $asset): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $cabangs = $canManageAll ? Cabang::orderBy('nama')->get() : Cabang::where('id', $user?->cabang_id)->get();

        $assetCoas = ChartOfAccount::where('code', 'like', '1210%')->orderBy('code')->get();
        if ($assetCoas->isEmpty()) {
            $assetCoas = ChartOfAccount::where('type', 'fixed_asset')->orderBy('code')->get();
        }

        $accumulatedCoas = ChartOfAccount::where('code', 'like', '1220%')->orderBy('code')->get();
        $expenseCoas = ChartOfAccount::where('code', 'like', '631%')->orderBy('code')->get();
        $products = Product::orderBy('name')->limit(100)->get(['id', 'sku', 'name', 'cost_price']);

        $hasJournals = $asset->hasPostedJournals();

        return view('keuangan.asset.edit', compact(
            'asset',
            'cabangs',
            'assetCoas',
            'accumulatedCoas',
            'expenseCoas',
            'products',
            'hasJournals',
            'canManageAll'
        ));
    }

    /**
     * Perbarui data aset tetap.
     */
    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $asset->fill($validated);

            // Hitung ulang parameter penyusutan
            $amounts = $asset->depreciationAmounts();
            $asset->annual_depreciation = $amounts['annual_depreciation'];
            $asset->monthly_depreciation = $amounts['monthly_depreciation'];
            $asset->book_value = max(0, $asset->purchase_cost - $asset->accumulated_depreciation);
            $asset->save();

            DB::commit();

            return redirect()->route('keuangan.asset.show', $asset)
                ->with('success', "Data aset '{$asset->name}' ({$asset->code}) berhasil diperbarui.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('AssetController@update failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Gagal memperbarui aset: ' . $e->getMessage());
        }
    }

    /**
     * Hapus (Soft Delete) data aset tetap.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        // Proteksi jika memiliki entri depresiasi aktif
        if ($asset->depreciationEntries()->where('status', '!=', 'reversed')->exists()) {
            return back()->with('error', 'Aset tidak dapat dihapus karena sudah memiliki riwayat penyusutan tercatat.');
        }

        try {
            $name = $asset->name;
            $code = $asset->code;
            $asset->delete();

            return redirect()->route('keuangan.asset.index')
                ->with('success', "Aset '{$name}' ({$code}) berhasil dihapus.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menghapus aset: ' . $e->getMessage());
        }
    }

    /**
     * Posting manual Jurnal Akuisisi ke Buku Besar jika belum pernah diposting.
     */
    public function postAcquisitionJournal(Asset $asset, Request $request): RedirectResponse
    {
        if ($asset->hasPostedJournals()) {
            return back()->with('error', 'Jurnal akuisisi untuk aset ini sudah pernah diposting.');
        }

        $creditCoaId = $request->input('credit_coa_id');

        try {
            $this->assetService->postAssetAcquisitionJournal($asset, $creditCoaId ? (int) $creditCoaId : null);

            return back()->with('success', 'Jurnal akuisisi aset berhasil diposting ke Buku Besar.');
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal memposting jurnal akuisisi: ' . $e->getMessage());
        }
    }

    /**
     * Hitung ulang nilai penyusutan tahunan & bulanan aset.
     */
    public function calculateDepreciation(Asset $asset): RedirectResponse
    {
        try {
            $asset->calculateDepreciation();

            return back()->with('success', 'Nilai penyusutan aset berhasil dihitung ulang.');
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menghitung penyusutan: ' . $e->getMessage());
        }
    }

    /**
     * Cetak PDF Kartu Identitas Aset Tetap / Label Barcode (A4 Portrait).
     */
    public function pdf(Asset $asset): Response
    {
        $asset->load(['cabang', 'product', 'assetCoa']);

        $pdf = Pdf::loadView('keuangan.asset.pdf', compact('asset'))
            ->setPaper('a4', 'portrait');

        $filename = "Kartu_Aset_{$asset->code}.pdf";
        return $pdf->stream($filename);
    }
}
