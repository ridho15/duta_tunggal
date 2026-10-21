<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Services\Dashboard\ExecutiveDashboardService;
use App\Support\MenuAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected ExecutiveDashboardService $executiveDashboardService
    ) {}

    /**
     * Tampilkan Beranda / Dashboard terpadu berbasis peran pengguna.
     * - Super Admin / Owner / Finance (OWN, FIN): Dashboard Eksekutif Bisnis (Mockup A1).
     * - Staf Operasional (Kasir, Sales, Gudang, dll): Beranda Tugas Saya (Mockup E1).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isSuper = MenuAccess::isSuper($user);
        $userCodes = MenuAccess::codesFor($user);

        // Akses Dashboard Eksekutif: Super Admin, Owner, Finance (OWN / FIN)
        $isExecutive = $isSuper || in_array('OWN', $userCodes) || in_array('FIN', $userCodes);

        if ($isExecutive) {
            return $this->executiveDashboard($request, $user, $isSuper);
        }

        return $this->operationalDashboard($request, $user, $isSuper);
    }

    /**
     * Dashboard Eksekutif Bisnis (Mockup A1 & A2).
     */
    protected function executiveDashboard(Request $request, $user, bool $isSuper)
    {
        $cabangId = $request->query('cabang_id');
        if ($cabangId === 'all' || $cabangId === '' || empty($cabangId)) {
            $cabangId = null;
        } elseif ($cabangId !== null) {
            $cabangId = (int) $cabangId;
        }

        // Jika user bukan super admin dan terikat cabang tertentu
        if (! $isSuper && $user->cabang_id) {
            $cabangId = $user->cabang_id;
        }

        $periode = $request->query('periode', 'today');
        if (! in_array($periode, ['today', '7d', 'month', 'custom'])) {
            $periode = 'today';
        }

        $startDateStr = $request->query('start_date');
        $endDateStr = $request->query('end_date');
        $forceRefresh = $request->has('refresh');

        $data = $this->executiveDashboardService->getDashboardData(
            $cabangId,
            $periode,
            $startDateStr,
            $endDateStr,
            $forceRefresh
        );

        $cabangs = Cabang::query()->orderBy('kode')->get(['id', 'kode', 'nama']);
        $activeCabangName = $cabangId
            ? ($cabangs->firstWhere('id', $cabangId)?->nama ?? 'Cabang')
            : 'Semua cabang';

        return view('dashboard', array_merge($data, [
            'isExecutive'      => true,
            'user'             => $user,
            'isSuper'          => $isSuper,
            'cabangs'          => $cabangs,
            'activeCabangName' => $activeCabangName,
        ]));
    }

    /**
     * Beranda Tugas Saya untuk Staf Operasional (Mockup E1).
     */
    protected function operationalDashboard(Request $request, $user, bool $isSuper)
    {
        $cabangId = $user->cabang_id;
        $today = Carbon::today();

        // Query Builder dasar untuk SO dengan filter cabang
        $soQuery = SaleOrder::query();
        $doQuery = DeliveryOrder::query();
        $sjQuery = SuratJalan::query();
        $receiptQuery = CustomerReceipt::query();
        $invoiceQuery = Invoice::query();

        if (! $isSuper && $cabangId) {
            $soQuery->where('cabang_id', $cabangId);
            $doQuery->where('cabang_id', $cabangId);
            $sjQuery->where('cabang_id', $cabangId);
            $receiptQuery->where('cabang_id', $cabangId);
            $invoiceQuery->where('cabang_id', $cabangId);
        }

        // 1. SO Menunggu Approve Kasir (Normal / Di Atas Batas Minimum)
        $soMenungguApprove = (clone $soQuery)
            ->where('status', 'request_approve')
            ->whereNull('cashier_approved_at')
            ->count();

        // 2. SO Khusus yang perlu tindakan atasan (misal kredit limit / diskon harga khusus / menunggu approve final)
        $soPerluAtasan = (clone $soQuery)
            ->where('status', 'request_approve')
            ->whereNotNull('cashier_approved_at')
            ->whereNull('approve_at')
            ->count();

        // 3. DO Menunggu Diproses Gudang
        $doMenungguGudang = (clone $doQuery)
            ->whereIn('status', ['draft', 'approved', 'request_approve'])
            ->count();

        // 4. Surat Jalan Siap Ditagih (Terbit tetapi belum punya Invoice)
        $sjSiapDitagih = (clone $sjQuery)
            ->where('status', SuratJalan::STATUS_ISSUED)
            ->get()
            ->filter(fn ($sj) => ! $sj->isInvoiced())
            ->count();

        // Ringkasan Selesai Hari Ini
        $soApprovedHariIni = (clone $soQuery)
            ->whereIn('status', ['approved', 'confirmed', 'partially_delivered', 'completed'])
            ->whereDate('updated_at', $today)
            ->count();

        $pembayaranHariIni = (clone $receiptQuery)
            ->whereDate('created_at', $today)
            ->count();

        $invoiceHariIni = (clone $invoiceQuery)
            ->whereDate('created_at', $today)
            ->count();

        // Total tugas aktif yang menunggu tindakan
        $tugasAktif = $soMenungguApprove + $soPerluAtasan + $doMenungguGudang + $sjSiapDitagih;

        // Ambil daftar SO teratas yang memerlukan tindakan untuk tabel / list preview
        $previewOrders = (clone $soQuery)
            ->with(['customer', 'createdBy'])
            ->whereIn('status', ['request_approve', 'draft'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'isExecutive'        => false,
            'user'               => $user,
            'todayDateFormatted' => Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y'),
            'soMenungguApprove'  => $soMenungguApprove,
            'soPerluAtasan'      => $soPerluAtasan,
            'doMenungguGudang'   => $doMenungguGudang,
            'sjSiapDitagih'      => $sjSiapDitagih,
            'tugasAktif'         => $tugasAktif,
            'soApprovedHariIni'  => $soApprovedHariIni,
            'pembayaranHariIni'  => $pembayaranHariIni,
            'invoiceHariIni'     => $invoiceHariIni,
            'previewOrders'      => $previewOrders,
        ]);
    }
}
