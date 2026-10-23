<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetDisposalRequest;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\Cabang;
use App\Services\AssetDisposalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AssetDisposalController extends Controller
{
    protected AssetDisposalService $disposalService;

    public function __construct(AssetDisposalService $disposalService)
    {
        $this->disposalService = $disposalService;
    }

    /**
     * Tampilkan daftar pelepasan/disposal aset tetap & 4 KPI Cards.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $type = $request->query('disposal_type', 'all');
        $cabangId = $request->query('cabang_id');
        $search = trim((string) $request->query('q', ''));

        // Query dasar
        $baseQuery = AssetDisposal::query();

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->whereHas('asset', fn($q) => $q->where('cabang_id', $user->cabang_id));
        } elseif ($cabangId) {
            $baseQuery->whereHas('asset', fn($q) => $q->where('cabang_id', $cabangId));
        }

        // 1. Hitung 4 KPI Utama
        $kpis = [
            'total_disposals' => (clone $baseQuery)->count(),
            'total_book_value' => (float) (clone $baseQuery)->sum('book_value_at_disposal'),
            'total_sale_price' => (float) (clone $baseQuery)->sum('sale_price'),
            'net_gain_loss' => (float) (clone $baseQuery)->sum('gain_loss_amount'),
        ];

        // 2. Filter list
        $query = (clone $baseQuery)->with([
            'asset.cabang',
            'asset.assetCoa',
            'approvedBy',
            'journalEntries',
        ]);

        if ($type && $type !== 'all') {
            $query->where('disposal_type', $type);
        }

        if ($search !== '') {
            $query->whereHas('asset', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $disposals = $query->orderBy('disposal_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $cabangs = $canManageAll ? Cabang::orderBy('nama')->get() : Cabang::where('id', $user?->cabang_id)->get();

        return view('keuangan.asset.disposal.index', compact('disposals', 'kpis', 'cabangs', 'canManageAll'));
    }

    /**
     * Formulir pencatatan pelepasan / penghapusan aset tetap.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $selectedAssetId = $request->query('asset_id');

        // Query aset aktif yang belum didisposal
        $assetQuery = Asset::where('status', 'active')
            ->whereDoesntHave('disposals', function ($q) {
                $q->whereIn('status', ['pending', 'completed']);
            })
            ->with(['cabang', 'assetCoa'])
            ->orderBy('name');

        if (!$canManageAll && $user?->cabang_id) {
            $assetQuery->where('cabang_id', $user->cabang_id);
        }

        $assets = $assetQuery->get();
        $selectedAsset = $selectedAssetId ? $assets->firstWhere('id', (int) $selectedAssetId) : null;

        return view('keuangan.asset.disposal.create', compact('assets', 'selectedAsset', 'selectedAssetId', 'canManageAll'));
    }

    /**
     * Simpan transaksi pelepasan aset, update status aset ke disposed, dan auto-post jurnal.
     */
    public function store(StoreAssetDisposalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $asset = Asset::findOrFail($validated['asset_id']);

        try {
            if ($request->hasFile('disposal_document')) {
                $path = $request->file('disposal_document')->store('asset-disposals', 'public');
                $validated['disposal_document'] = $path;
            }

            $disposal = $this->disposalService->createDisposal($asset, $validated);

            $gainLossText = $disposal->gain_loss_amount >= 0
                ? 'Laba Pelepasan: Rp ' . number_format($disposal->gain_loss_amount, 0, ',', '.')
                : 'Rugi Pelepasan: Rp ' . number_format(abs($disposal->gain_loss_amount), 0, ',', '.');

            return redirect()->route('keuangan.asset.disposal.show', $disposal)
                ->with('success', "Pelepasan aset '{$asset->name}' ({$asset->code}) berhasil dicatat. Status aset telah diubah menjadi 'Disposed' dan Jurnal Pelepasan ({$gainLossText}) telah diposting ke Buku Besar.");
        } catch (Throwable $e) {
            Log::error('AssetDisposalController@store failed', [
                'error' => $e->getMessage(),
                'asset_id' => $asset->id,
            ]);

            return back()->withInput()->with('error', 'Gagal memproses pelepasan aset: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan rincian pelepasan aset, kartu kalkulasi laba/rugi, dan entri jurnal buku besar.
     */
    public function show(AssetDisposal $disposal): View
    {
        $disposal->load([
            'asset.cabang',
            'asset.assetCoa',
            'asset.accumulatedDepreciationCoa',
            'approvedBy',
            'journalEntries.coa',
        ]);

        return view('keuangan.asset.disposal.show', compact('disposal'));
    }

    /**
     * Cetak Berita Acara Pelepasan & Penghapusan Aset Tetap (PDF A4 Portrait).
     */
    public function pdf(AssetDisposal $disposal): Response
    {
        $disposal->load([
            'asset.cabang',
            'asset.assetCoa',
            'asset.accumulatedDepreciationCoa',
            'approvedBy',
            'journalEntries.coa',
        ]);

        $pdf = Pdf::loadView('keuangan.asset.disposal.pdf', compact('disposal'))
            ->setPaper('a4', 'portrait');

        $filename = "Berita_Acara_Pelepasan_Aset_{$disposal->id}.pdf";
        return $pdf->stream($filename);
    }
}
