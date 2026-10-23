<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetTransferRequest;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Services\AssetTransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AssetTransferController extends Controller
{
    protected AssetTransferService $transferService;

    public function __construct(AssetTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Tampilkan daftar mutasi transfer aset antar cabang & 4 KPI Cards.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $status = $request->query('status', 'all');
        $fromCabangId = $request->query('from_cabang_id');
        $toCabangId = $request->query('to_cabang_id');
        $search = trim((string) $request->query('q', ''));

        // Query dasar untuk KPI dan list
        $baseQuery = AssetTransfer::query();

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('from_cabang_id', $user->cabang_id)
                  ->orWhere('to_cabang_id', $user->cabang_id);
            });
        }

        // 1. Hitung 4 KPI Utama
        $kpis = [
            'total_transfers' => (clone $baseQuery)->count(),
            'pending_count' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved_count' => (clone $baseQuery)->where('status', 'approved')->count(),
            'completed_count' => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        // 2. Filter list
        $query = (clone $baseQuery)->with([
            'asset.cabang',
            'fromCabang',
            'toCabang',
            'requestedBy',
            'approvedBy',
            'completedBy',
        ]);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($fromCabangId) {
            $query->where('from_cabang_id', $fromCabangId);
        }

        if ($toCabangId) {
            $query->where('to_cabang_id', $toCabangId);
        }

        if ($search !== '') {
            $query->whereHas('asset', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('keuangan.asset.transfer.index', compact('transfers', 'kpis', 'cabangs', 'canManageAll'));
    }

    /**
     * Tampilkan formulir pengajuan mutasi aset antar cabang.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        $selectedAssetId = $request->query('asset_id');

        // Query aset aktif yang tidak sedang dalam transfer pending/approved
        $assetQuery = Asset::where('status', 'active')
            ->whereDoesntHave('transfers', function ($q) {
                $q->whereIn('status', ['pending', 'approved']);
            })
            ->with('cabang')
            ->orderBy('name');

        if (!$canManageAll && $user?->cabang_id) {
            $assetQuery->where('cabang_id', $user->cabang_id);
        }

        $assets = $assetQuery->get();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('keuangan.asset.transfer.create', compact('assets', 'cabangs', 'selectedAssetId', 'canManageAll'));
    }

    /**
     * Simpan pengajuan mutasi aset antar cabang baru.
     */
    public function store(StoreAssetTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $asset = Asset::findOrFail($validated['asset_id']);

        try {
            $data = [
                'transfer_date' => $validated['transfer_date'],
                'reason' => $validated['reason'],
            ];

            if ($request->hasFile('transfer_document')) {
                $path = $request->file('transfer_document')->store('asset-transfers', 'public');
                $data['transfer_document'] = $path;
            }

            $transfer = $this->transferService->createTransferRequest(
                $asset,
                (int) $validated['to_cabang_id'],
                $data
            );

            return redirect()->route('keuangan.asset.transfer.show', $transfer)
                ->with('success', "Pengajuan mutasi aset '{$asset->name}' ke cabang {$transfer->toCabang->nama} berhasil dibuat dan menunggu persetujuan (Pending).");
        } catch (Throwable $e) {
            Log::error('AssetTransferController@store failed', [
                'error' => $e->getMessage(),
                'asset_id' => $asset->id,
            ]);

            return back()->withInput()->with('error', 'Gagal mengajukan mutasi aset: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail mutasi transfer aset, timeline status, dan dokumen lampiran.
     */
    public function show(AssetTransfer $transfer): View
    {
        $transfer->load([
            'asset.cabang',
            'fromCabang',
            'toCabang',
            'requestedBy',
            'approvedBy',
            'completedBy',
        ]);

        return view('keuangan.asset.transfer.show', compact('transfer'));
    }

    /**
     * Setujui pengajuan mutasi aset (Approve).
     */
    public function approve(AssetTransfer $transfer): RedirectResponse
    {
        try {
            $this->transferService->approveTransfer($transfer);

            return back()->with('success', "Pengajuan mutasi aset '{$transfer->asset->name}' berhasil disetujui (Approved) dan siap untuk proses pengiriman/penerimaan fisik.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menyetujui mutasi transfer: ' . $e->getMessage());
        }
    }

    /**
     * Selesaikan mutasi transfer aset dan perbarui cabang lokasi aset (Complete).
     */
    public function complete(AssetTransfer $transfer): RedirectResponse
    {
        try {
            $this->transferService->completeTransfer($transfer);

            return back()->with('success', "Mutasi transfer aset '{$transfer->asset->name}' telah selesai. Lokasi aset resmi dipindahkan ke cabang {$transfer->toCabang->nama}.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menyelesaikan mutasi transfer: ' . $e->getMessage());
        }
    }

    /**
     * Batalkan pengajuan mutasi transfer aset (Cancel).
     */
    public function cancel(AssetTransfer $transfer, Request $request): RedirectResponse
    {
        $request->validate([
            'cancel_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan mutasi wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan minimal 5 karakter.',
            'cancel_reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        try {
            $this->transferService->cancelTransfer($transfer, $request->input('cancel_reason'));

            return back()->with('success', "Pengajuan mutasi transfer aset '{$transfer->asset->name}' berhasil dibatalkan.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membatalkan mutasi transfer: ' . $e->getMessage());
        }
    }

    /**
     * Cetak Berita Acara / Surat Pengantar Mutasi Aset (PDF A4 Portrait).
     */
    public function pdf(AssetTransfer $transfer): Response
    {
        $transfer->load([
            'asset.cabang',
            'asset.assetCoa',
            'fromCabang',
            'toCabang',
            'requestedBy',
            'approvedBy',
            'completedBy',
        ]);

        $pdf = Pdf::loadView('keuangan.asset.transfer.pdf', compact('transfer'))
            ->setPaper('a4', 'portrait');

        $filename = "Berita_Acara_Mutasi_Aset_{$transfer->id}.pdf";
        return $pdf->stream($filename);
    }
}
