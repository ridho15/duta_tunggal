<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\Vehicle;
use App\Services\DocumentNumberService;
use App\Services\SequentialNumberGenerator;
use App\Services\SuratJalanDocumentBuilder;
use App\Services\SuratJalanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuratJalanController extends Controller
{
    protected SuratJalanService $suratJalanService;
    protected SuratJalanDocumentBuilder $documentBuilder;

    public function __construct(
        SuratJalanService $suratJalanService,
        SuratJalanDocumentBuilder $documentBuilder
    ) {
        $this->suratJalanService = $suratJalanService;
        $this->documentBuilder = $documentBuilder;
    }

    /**
     * Menampilkan daftar Surat Jalan dengan filter tab & pencarian.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');

        // Hitung live counter tab status
        $counts = [
            'semua' => SuratJalan::count(),
            'terbit' => SuratJalan::where('status', SuratJalan::STATUS_ISSUED)->count(),
            'draft' => SuratJalan::where('status', SuratJalan::STATUS_DRAFT)->count(),
            'dibatalkan' => SuratJalan::where('status', SuratJalan::STATUS_CANCELLED)->count(),
        ];

        $query = SuratJalan::with([
            'cabang',
            'deliveryOrder.salesOrders.customer',
            'deliveryOrder.warehouse',
            'deliverySchedules.driver',
            'deliverySchedules.vehicle',
        ])->latest('id');

        // Filter status tab
        if ($tab === 'terbit') {
            $query->where('status', SuratJalan::STATUS_ISSUED);
        } elseif ($tab === 'draft') {
            $query->where('status', SuratJalan::STATUS_DRAFT);
        } elseif ($tab === 'dibatalkan') {
            $query->where('status', SuratJalan::STATUS_CANCELLED);
        }

        // Filter cabang
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        // Pencarian multi-kolom
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sj_number', 'like', "%{$search}%")
                  ->orWhere('signed_by', 'like', "%{$search}%")
                  ->orWhereHas('deliveryOrder', function ($doQ) use ($search) {
                      $doQ->where('do_number', 'like', "%{$search}%")
                          ->orWhereHas('salesOrders', function ($soQ) use ($search) {
                              $soQ->where('so_number', 'like', "%{$search}%")
                                  ->orWhereHas('customer', function ($cQ) use ($search) {
                                      $cQ->where('name', 'like', "%{$search}%")
                                         ->orWhere('code', 'like', "%{$search}%");
                                  });
                          });
                  });
            });
        }

        $suratJalans = $query->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->get();

        return view('penjualan.sj.index', compact('suratJalans', 'counts', 'tab', 'search', 'cabangs', 'cabangId'));
    }

    /**
     * Form Buat Surat Jalan dari DO (Pilih Customer > SO > centang DO-DO).
     */
    public function create(Request $request): View
    {
        $soId = $request->query('so_id');
        $doId = $request->query('do_id');

        $activeCabang = Auth::user()->cabang ?? Cabang::first();

        // Cari DO yang berstatus approved, belum diretur, dan belum terikat di SJ aktif
        $availableDOs = DeliveryOrder::with([
            'salesOrders.customer',
            'warehouse',
            'deliveryOrderItem.product.uom',
        ])
        ->whereIn('status', ['approved', 'confirmed', 'partial'])
        ->whereNull('returned_at')
        ->whereDoesntHave('suratJalan', function ($q) {
            $q->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES);
        })
        ->orderBy('do_number', 'desc')
        ->get();

        // Kelompokkan DO berdasarkan SO untuk mempermudah pemilihan
        $groupedDOs = [];
        foreach ($availableDOs as $do) {
            foreach ($do->salesOrders as $so) {
                if (!isset($groupedDOs[$so->id])) {
                    $groupedDOs[$so->id] = [
                        'so_id' => $so->id,
                        'so_number' => $so->so_number,
                        'customer' => $so->customer,
                        'cabang' => $so->cabang,
                        'delivery_orders' => [],
                    ];
                }
                $groupedDOs[$so->id]['delivery_orders'][] = $do;
            }
        }

        $drivers = Driver::orderBy('name')->get();
        $vehicles = Vehicle::orderBy('plate')->get();

        return view('penjualan.sj.create', compact('groupedDOs', 'availableDOs', 'drivers', 'vehicles', 'activeCabang', 'soId', 'doId'));
    }

    /**
     * Menyimpan dan menerbitkan Surat Jalan resmi.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'delivery_order_ids' => 'required|array|min:1',
            'delivery_order_ids.*' => 'exists:delivery_orders,id',
            'issued_at' => 'required|date',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'delivery_method' => 'required|in:internal,ekspedisi',
            'driver_id' => 'nullable|exists:drivers,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'expedition_name' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $deliveryOrders = DeliveryOrder::whereIn('id', $validated['delivery_order_ids'])->get();

        // Validasi konsolidasi aturan bisnis Duta Tunggal
        $this->suratJalanService->assertDeliveryOrdersUsable($deliveryOrders);

        $cabangId = $validated['cabang_id'] ?? $deliveryOrders->first()->cabang_id ?? Auth::user()->cabang_id ?? 1;

        $suratJalan = DB::transaction(function () use ($validated, $deliveryOrders, $cabangId) {
            $sjNumber = $this->suratJalanService->generateCode($cabangId);

            $sj = SuratJalan::create([
                'sj_number' => $sjNumber,
                'issued_at' => Carbon::parse($validated['issued_at']),
                'status' => SuratJalan::STATUS_ISSUED,
                'signed_by' => Auth::id(),
                'created_by' => Auth::id(),
                'cabang_id' => $cabangId,
            ]);

            // Hubungkan DO
            $sj->deliveryOrder()->attach($deliveryOrders->pluck('id'));

            // Otomatis tandai DO terkirim & diterima
            $this->suratJalanService->markDeliveryOrdersReceived($sj);

            // Buat record DeliverySchedule agar terhubung rapi ke logistik & cetakan PDF
            $hasScheduleInfo = !empty($validated['driver_id']) || !empty($validated['vehicle_id']) || !empty($validated['expedition_name']);
            if ($hasScheduleInfo) {
                $driver = !empty($validated['driver_id']) ? Driver::find($validated['driver_id']) : null;
                $vehicle = !empty($validated['vehicle_id']) ? Vehicle::find($validated['vehicle_id']) : null;

                $scheduleNum = SequentialNumberGenerator::generate('delivery_schedules', 'schedule_number', 'SCH-', 4, 'Ymd');

                $schedule = DeliverySchedule::create([
                    'schedule_number' => $scheduleNum,
                    'scheduled_date' => Carbon::parse($validated['issued_at'])->format('Y-m-d'),
                    'delivery_method' => $validated['delivery_method'],
                    'driver_id' => $driver?->id,
                    'vehicle_id' => $vehicle?->id,
                    'driver_name' => $driver ? $driver->name : ($validated['expedition_name'] ?? '-'),
                    'vehicle_info' => $vehicle ? $vehicle->plate : ($validated['tracking_number'] ?? '-'),
                    'tracking_number' => $validated['tracking_number'] ?? null,
                    'status' => 'delivered',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id(),
                    'cabang_id' => $cabangId,
                ]);

                $sj->deliverySchedules()->attach($schedule->id);
            }

            return $sj;
        });

        return redirect()->route('penjualan.sj.show', $suratJalan->id)
            ->with('success', "Surat Jalan {$suratJalan->sj_number} berhasil diterbitkan.");
    }

    /**
     * Menampilkan detail Surat Jalan.
     */
    public function show(SuratJalan $suratJalan): View
    {
        $suratJalan->loadMissing(SuratJalanDocumentBuilder::RELATIONS);
        $doc = $this->documentBuilder->build($suratJalan);

        // Ambil SO terkait untuk tombol aksi
        $primarySO = $suratJalan->deliveryOrder->flatMap->salesOrders->first();

        return view('penjualan.sj.show', compact('suratJalan', 'doc', 'primarySO'));
    }

    /**
     * Pratinjau / Cetak PDF Surat Jalan (tanpa harga, ber-kop dan 4 TTD).
     */
    public function pdf(SuratJalan $suratJalan)
    {
        $suratJalan->loadMissing(SuratJalanDocumentBuilder::RELATIONS);
        $doc = $this->documentBuilder->build($suratJalan);

        $pdf = Pdf::loadView('pdf.surat-jalan', compact('doc'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("Surat-Jalan-{$suratJalan->sj_number}.pdf");
    }

    /**
     * Batalkan Surat Jalan resmi (dengan alasan wajib).
     */
    public function cancel(Request $request, SuratJalan $suratJalan): RedirectResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|min:5|max:255',
        ]);

        try {
            $this->suratJalanService->cancel($suratJalan, $validated['cancel_reason'], Auth::id());
            return redirect()->route('penjualan.sj.show', $suratJalan->id)
                ->with('success', "Surat Jalan {$suratJalan->sj_number} berhasil dibatalkan. Delivery Order terkait kini telah dibebaskan.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }
}
