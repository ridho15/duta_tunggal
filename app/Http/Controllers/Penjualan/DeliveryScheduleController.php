<?php

namespace App\Http\Controllers\Penjualan;

use App\Exceptions\DeliveryOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Penjualan\StoreDeliveryScheduleRequest;
use App\Http\Requests\Penjualan\UpdateDeliveryScheduleRequest;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\SuratJalan;
use App\Models\Vehicle;
use App\Services\DeliveryScheduleService;
use App\Support\MenuAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DeliveryScheduleController extends Controller
{
    public function __construct(
        protected DeliveryScheduleService $scheduleService
    ) {}

    /**
     * Otorisasi hak akses modul Penjadwalan Armada & Pengiriman (Role: OWN, ADP).
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'ADP'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Penjadwalan Armada & Pengiriman.');
    }

    /**
     * Tampilkan daftar Jadwal Pengiriman dengan KPI & filter.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');
        $method = $request->query('method');
        $driverId = $request->query('driver_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Live KPI Counters
        $counts = [
            'semua' => DeliverySchedule::count(),
            'pending' => DeliverySchedule::where('status', 'pending')->count(),
            'on_the_way' => DeliverySchedule::where('status', 'on_the_way')->count(),
            'delivered' => DeliverySchedule::where('status', 'delivered')->count(),
            'failed' => DeliverySchedule::where('status', 'failed')->count(),
            'cancelled' => DeliverySchedule::where('status', 'cancelled')->count(),
        ];

        $query = DeliverySchedule::with([
            'cabang',
            'driver',
            'vehicle',
            'createdBy',
            'suratJalan.deliveryOrder.salesOrders.customer',
            'deliveryOrders.salesOrders.customer',
        ])->latest('scheduled_date')->latest('id');

        // Filter tab status
        if ($tab !== 'semua' && array_key_exists($tab, $counts)) {
            $query->where('status', $tab);
        }

        // Filter cabang
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        // Filter metode
        if ($method) {
            $query->where('delivery_method', $method);
        }

        // Filter driver
        if ($driverId) {
            $query->where('driver_id', $driverId);
        }

        // Filter tanggal keberangkatan
        if ($startDate) {
            $query->whereDate('scheduled_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('scheduled_date', '<=', $endDate);
        }

        // Pencarian multi-kolom
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('schedule_number', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_info', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('driver', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('vehicle', function ($vq) use ($search) {
                        $vq->where('plate', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%");
                    })
                    ->orWhereHas('suratJalan', function ($sjq) use ($search) {
                        $sjq->where('sj_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('deliveryOrders', function ($doq) use ($search) {
                        $doq->where('do_number', 'like', "%{$search}%");
                    });
            });
        }

        $schedules = $query->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->get();
        $drivers = Driver::orderBy('name')->get();
        $vehicles = Vehicle::orderBy('plate')->get();

        return view('penjualan.schedules.index', compact(
            'schedules',
            'counts',
            'tab',
            'search',
            'cabangId',
            'method',
            'driverId',
            'startDate',
            'endDate',
            'cabangs',
            'drivers',
            'vehicles'
        ));
    }

    /**
     * Form pembuatan jadwal pengiriman baru.
     */
    public function create(Request $request): View
    {
        $this->authorizeAccess();

        $user = Auth::user();
        $cabangId = $request->query('cabang_id', $user->cabang_id ?? Cabang::first()?->id);

        $defaultScheduleNumber = DeliveryScheduleService::generateStaticScheduleNumber($cabangId);
        $cabangs = Cabang::where('status', true)->get();
        $drivers = Driver::orderBy('name')->get();
        $vehicles = Vehicle::orderBy('plate')->get();

        // Ambil kandidat Surat Jalan resmi yang belum ditautkan ke jadwal aktif
        $candidateSuratJalans = SuratJalan::with([
            'deliveryOrder.salesOrders.customer',
            'cabang',
        ])
            ->where('status', SuratJalan::STATUS_ISSUED)
            ->whereDoesntHave('deliverySchedules', function ($q) {
                $q->whereIn('status', ['pending', 'on_the_way', 'delivered']);
            })
            ->latest('id')
            ->limit(50)
            ->get();

        // Ambil kandidat Delivery Order siap kirim yang belum ditautkan ke jadwal aktif
        $candidateDeliveryOrders = DeliveryOrder::with([
            'salesOrders.customer',
            'warehouse',
            'cabang',
        ])
            ->whereIn('status', ['approved', 'confirmed', 'partial', 'delivery_failed'])
            ->whereDoesntHave('deliverySchedules', function ($q) {
                $q->whereIn('status', ['pending', 'on_the_way', 'delivered']);
            })
            ->latest('id')
            ->limit(50)
            ->get();

        return view('penjualan.schedules.create', compact(
            'defaultScheduleNumber',
            'cabangs',
            'drivers',
            'vehicles',
            'candidateSuratJalans',
            'candidateDeliveryOrders',
            'cabangId'
        ));
    }

    /**
     * Simpan jadwal pengiriman baru.
     */
    public function store(StoreDeliveryScheduleRequest $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validated();
        $this->scheduleService->validateSender($validated);

        try {
            $schedule = DB::transaction(function () use ($validated) {
                $scheduleNumber = ! empty($validated['schedule_number'])
                    ? $validated['schedule_number']
                    : DeliveryScheduleService::generateStaticScheduleNumber($validated['cabang_id']);

                $schedule = DeliverySchedule::create([
                    'schedule_number' => $scheduleNumber,
                    'scheduled_date' => Carbon::parse($validated['scheduled_date']),
                    'delivery_method' => $validated['delivery_method'],
                    'driver_id' => in_array($validated['delivery_method'], DeliverySchedule::INTERNAL_METHODS) ? ($validated['driver_id'] ?? null) : null,
                    'vehicle_id' => in_array($validated['delivery_method'], DeliverySchedule::INTERNAL_METHODS) ? ($validated['vehicle_id'] ?? null) : null,
                    'driver_name' => $validated['driver_name'] ?? null,
                    'vehicle_info' => $validated['vehicle_info'] ?? null,
                    'tracking_number' => $validated['tracking_number'] ?? null,
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id(),
                    'cabang_id' => $validated['cabang_id'],
                ]);

                if (! empty($validated['surat_jalan_ids'])) {
                    $schedule->suratJalan()->sync($validated['surat_jalan_ids']);
                }

                if (! empty($validated['delivery_order_ids'])) {
                    $schedule->deliveryOrders()->sync($validated['delivery_order_ids']);
                }

                return $schedule;
            });

            return redirect()->route('penjualan.schedules.show', $schedule->id)
                ->with('success', "Jadwal pengiriman {$schedule->schedule_number} berhasil dibuat.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('DeliveryScheduleController store error: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);

            return back()->with('error', 'Terjadi kesalahan saat menyimpan jadwal pengiriman: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Tampilkan detail manifes jadwal pengiriman.
     */
    public function show(DeliverySchedule $deliverySchedule): View
    {
        $this->authorizeAccess();

        $deliverySchedule->loadMissing([
            'cabang',
            'driver',
            'vehicle',
            'createdBy',
            'suratJalan.deliveryOrder.salesOrders.customer',
            'suratJalan.deliveryOrder.warehouse',
            'deliveryOrders.salesOrders.customer',
            'deliveryOrders.warehouse',
            'deliveryOrders.deliveryOrderItem.product',
        ]);

        $relatedDOs = $deliverySchedule->relatedDeliveryOrders();
        $relatedSJs = $deliverySchedule->relatedSuratJalan();

        return view('penjualan.schedules.show', compact('deliverySchedule', 'relatedDOs', 'relatedSJs'));
    }

    /**
     * Form edit jadwal pengiriman (hanya berlaku saat status pending).
     */
    public function edit(DeliverySchedule $deliverySchedule): View|RedirectResponse
    {
        $this->authorizeAccess();

        if ($deliverySchedule->status !== 'pending') {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Jadwal pengiriman yang sudah berjalan atau selesai tidak dapat diedit.');
        }

        $deliverySchedule->loadMissing(['suratJalan', 'deliveryOrders']);

        $cabangs = Cabang::where('status', true)->get();
        $drivers = Driver::orderBy('name')->get();
        $vehicles = Vehicle::orderBy('plate')->get();

        $attachedSjIds = $deliverySchedule->suratJalan->pluck('id')->all();
        $attachedDoIds = $deliverySchedule->deliveryOrders->pluck('id')->all();

        $candidateSuratJalans = SuratJalan::with([
            'deliveryOrder.salesOrders.customer',
            'cabang',
        ])
            ->where('status', SuratJalan::STATUS_ISSUED)
            ->where(function ($q) use ($attachedSjIds) {
                $q->whereDoesntHave('deliverySchedules', function ($sq) {
                    $sq->whereIn('status', ['pending', 'on_the_way', 'delivered']);
                })->orWhereIn('id', $attachedSjIds);
            })
            ->latest('id')
            ->get();

        $candidateDeliveryOrders = DeliveryOrder::with([
            'salesOrders.customer',
            'warehouse',
            'cabang',
        ])
            ->where(function ($q) use ($attachedDoIds) {
                $q->whereIn('status', ['approved', 'confirmed', 'partial', 'delivery_failed'])
                    ->whereDoesntHave('deliverySchedules', function ($sq) {
                        $sq->whereIn('status', ['pending', 'on_the_way', 'delivered']);
                    })->orWhereIn('id', $attachedDoIds);
            })
            ->latest('id')
            ->get();

        return view('penjualan.schedules.edit', compact(
            'deliverySchedule',
            'cabangs',
            'drivers',
            'vehicles',
            'candidateSuratJalans',
            'candidateDeliveryOrders',
            'attachedSjIds',
            'attachedDoIds'
        ));
    }

    /**
     * Perbarui jadwal pengiriman.
     */
    public function update(UpdateDeliveryScheduleRequest $request, DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $this->authorizeAccess();

        if ($deliverySchedule->status !== 'pending') {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Jadwal pengiriman yang sudah berjalan atau selesai tidak dapat diperbarui.');
        }

        $validated = $request->validated();
        $this->scheduleService->validateSender($validated);

        try {
            DB::transaction(function () use ($deliverySchedule, $validated) {
                $deliverySchedule->update([
                    'schedule_number' => $validated['schedule_number'] ?: $deliverySchedule->schedule_number,
                    'scheduled_date' => Carbon::parse($validated['scheduled_date']),
                    'delivery_method' => $validated['delivery_method'],
                    'driver_id' => in_array($validated['delivery_method'], DeliverySchedule::INTERNAL_METHODS) ? ($validated['driver_id'] ?? null) : null,
                    'vehicle_id' => in_array($validated['delivery_method'], DeliverySchedule::INTERNAL_METHODS) ? ($validated['vehicle_id'] ?? null) : null,
                    'driver_name' => $validated['driver_name'] ?? null,
                    'vehicle_info' => $validated['vehicle_info'] ?? null,
                    'tracking_number' => $validated['tracking_number'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'cabang_id' => $validated['cabang_id'],
                ]);

                $deliverySchedule->suratJalan()->sync($validated['surat_jalan_ids'] ?? []);
                $deliverySchedule->deliveryOrders()->sync($validated['delivery_order_ids'] ?? []);
            });

            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('success', "Jadwal pengiriman {$deliverySchedule->schedule_number} berhasil diperbarui.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('DeliveryScheduleController update error: ' . $e->getMessage(), [
                'exception' => $e,
                'schedule_id' => $deliverySchedule->id,
            ]);

            return back()->with('error', 'Terjadi kesalahan saat memperbarui jadwal: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Hapus jadwal pengiriman (hanya pending atau dibatalkan).
     */
    public function destroy(DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $this->authorizeAccess();

        if (! in_array($deliverySchedule->status, ['pending', 'cancelled'])) {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Hanya jadwal yang masih menunggu keberangkatan atau sudah dibatalkan yang dapat dihapus.');
        }

        $scheduleNumber = $deliverySchedule->schedule_number;
        $deliverySchedule->delete();

        return redirect()->route('penjualan.schedules.index')
            ->with('success', "Jadwal pengiriman {$scheduleNumber} berhasil dihapus.");
    }

    /**
     * Mulai Pengiriman: Armada berangkat menuju lokasi tujuan.
     * DO terkait berubah status menjadi 'sent' dan stok fisik berkurang di gudang.
     */
    public function start(DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $this->authorizeAccess();

        if ($deliverySchedule->status !== 'pending') {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Hanya jadwal berstatus Menunggu Keberangkatan yang dapat dimulai.');
        }

        try {
            $this->scheduleService->guardStatusChange($deliverySchedule, $deliverySchedule->status, 'on_the_way');

            $startedCount = 0;
            DB::transaction(function () use ($deliverySchedule, &$startedCount) {
                $deliverySchedule->update(['status' => 'on_the_way']);
                $startedCount = $this->scheduleService->startRelatedDeliveryOrders($deliverySchedule);
            });

            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('success', "Pengiriman jadwal {$deliverySchedule->schedule_number} berhasil dimulai ({$startedCount} DO diberangkatkan).");
        } catch (DeliveryOrderTransitionException $e) {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('DeliverySchedule start error: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Gagal memulai pengiriman: ' . $e->getMessage());
        }
    }

    /**
     * Selesaikan Pengiriman: Armada tiba di tujuan & barang diserahkan ke pelanggan.
     * DO terkait berubah menjadi 'completed' dan pembukuan/faktur terpicu otomatis.
     */
    public function complete(DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $this->authorizeAccess();

        if (! in_array($deliverySchedule->status, ['on_the_way', 'partial_delivered'])) {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Jadwal belum dimulai. Mulai pengiriman terlebih dahulu sebelum menandai selesai.');
        }

        try {
            $this->scheduleService->guardStatusChange($deliverySchedule, $deliverySchedule->status, 'delivered');

            $completedCount = 0;
            DB::transaction(function () use ($deliverySchedule, &$completedCount) {
                $deliverySchedule->update(['status' => 'delivered']);
                $completedCount = $this->scheduleService->completeRelatedDeliveryOrders($deliverySchedule);
            });

            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('success', "Jadwal {$deliverySchedule->schedule_number} berhasil diselesaikan ({$completedCount} DO selesai & jurnal otomatis dibukukan).");
        } catch (DeliveryOrderTransitionException $e) {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('DeliverySchedule complete error: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Gagal menyelesaikan pengiriman: ' . $e->getMessage());
        }
    }

    /**
     * Tandai Pengiriman Gagal: Kendaraan mogok / customer tutup / penolakan fisik.
     * DO yang sempat 'sent' dikembalikan menjadi 'delivery_failed' dan stok fisik kembali ke gudang.
     */
    public function fail(Request $request, DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $this->authorizeAccess();

        if ($deliverySchedule->status !== 'on_the_way') {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Hanya jadwal yang sedang dalam perjalanan yang dapat ditandai gagal.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:255',
        ], [
            'reason.required' => 'Alasan kegagalan pengiriman wajib diisi.',
            'reason.min' => 'Alasan kegagalan minimal 5 karakter.',
        ]);

        try {
            $deliverySchedule->transitionReason = $validated['reason'];

            DB::transaction(function () use ($deliverySchedule, $validated) {
                $existingNotes = $deliverySchedule->notes ? $deliverySchedule->notes . "\n" : '';
                $deliverySchedule->update([
                    'status' => 'failed',
                    'notes' => $existingNotes . '[PENGIRIMAN GAGAL]: ' . $validated['reason'],
                ]);
                $this->scheduleService->failRelatedDeliveryOrders($deliverySchedule);
            });

            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('success', "Jadwal {$deliverySchedule->schedule_number} ditandai gagal. Stok DO terkait telah dikembalikan ke gudang.");
        } catch (Throwable $e) {
            Log::error('DeliverySchedule fail error: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Gagal memproses status gagal pengiriman: ' . $e->getMessage());
        }
    }

    /**
     * Batalkan Jadwal Pengiriman (hanya saat pending).
     */
    public function cancel(Request $request, DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $this->authorizeAccess();

        if ($deliverySchedule->status !== 'pending') {
            return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
                ->with('error', 'Hanya jadwal yang masih menunggu keberangkatan yang dapat dibatalkan.');
        }

        $reason = $request->input('cancel_reason', 'Dibatalkan oleh staf logistik.');

        $existingNotes = $deliverySchedule->notes ? $deliverySchedule->notes . "\n" : '';
        $deliverySchedule->update([
            'status' => 'cancelled',
            'notes' => $existingNotes . '[DIBATALKAN]: ' . $reason,
        ]);

        return redirect()->route('penjualan.schedules.show', $deliverySchedule->id)
            ->with('success', "Jadwal pengiriman {$deliverySchedule->schedule_number} berhasil dibatalkan.");
    }

    /**
     * Cetak / Pratinjau Surat Kerja Driver (Work Order) PDF.
     */
    public function pdf(DeliverySchedule $deliverySchedule)
    {
        $this->authorizeAccess();

        $deliverySchedule->loadMissing([
            'cabang',
            'driver',
            'vehicle',
            'suratJalan.deliveryOrder.salesOrders.customer',
            'deliveryOrders.salesOrders.customer',
            'deliveryOrders.deliveryOrderItem.product.uom',
        ]);

        $deliveryOrders = $deliverySchedule->relatedDeliveryOrders();

        $pdf = Pdf::loadView('pdf.delivery-schedule-work-order', [
            'schedule' => $deliverySchedule,
            'deliveryOrders' => $deliveryOrders,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("surat-tugas-driver-{$deliverySchedule->schedule_number}.pdf");
    }

    /**
     * Endpoint tambah Driver cepat via AJAX/Form Modal.
     */
    public function storeDriver(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'license' => 'nullable|string|max:50',
            'cabang_id' => 'nullable|exists:cabangs,id',
        ], [
            'name.required' => 'Nama driver wajib diisi.',
        ]);

        $cabangId = $validated['cabang_id'] ?? Auth::user()->cabang_id ?? Cabang::first()?->id;

        $driver = Driver::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'license' => $validated['license'] ?? null,
            'cabang_id' => $cabangId,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Driver {$driver->name} berhasil ditambahkan.",
                'driver' => $driver,
            ]);
        }

        return back()->with('success', "Driver {$driver->name} berhasil ditambahkan.");
    }

    /**
     * Endpoint tambah Kendaraan cepat via AJAX/Form Modal.
     */
    public function storeVehicle(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'plate' => 'required|string|max:50',
            'type' => 'nullable|string|max:50',
            'capacity' => 'nullable|string|max:50',
            'cabang_id' => 'nullable|exists:cabangs,id',
        ], [
            'plate.required' => 'Nomor plat kendaraan wajib diisi.',
        ]);

        $cabangId = $validated['cabang_id'] ?? Auth::user()->cabang_id ?? Cabang::first()?->id;

        $vehicle = Vehicle::create([
            'plate' => strtoupper(trim($validated['plate'])),
            'type' => $validated['type'] ?? 'Truck',
            'capacity' => $validated['capacity'] ?? null,
            'cabang_id' => $cabangId,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Kendaraan {$vehicle->plate} berhasil ditambahkan.",
                'vehicle' => $vehicle,
            ]);
        }

        return back()->with('success', "Kendaraan {$vehicle->plate} berhasil ditambahkan.");
    }
}
