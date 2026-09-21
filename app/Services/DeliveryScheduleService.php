<?php

namespace App\Services;

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeliveryScheduleService
{
    public function generateScheduleNumber(): string
    {
        return static::generateStaticScheduleNumber();
    }

    public static function generateStaticScheduleNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'SCH-' . $date . '-';

        $max = DeliverySchedule::withoutGlobalScopes()
            ->where('schedule_number', 'like', $prefix . '%')
            ->max('schedule_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validasi pengirim di SERVER (bukan hanya `required` di form):
     *  - internal / kurir internal : driver & kendaraan harus ada di master (dan belum dihapus);
     *  - ekspedisi                 : nama driver/ekspedisi wajib; driver & kendaraan master tidak dibutuhkan.
     *
     * @param  array<string, mixed>  $data  data form jadwal
     * @param  string  $prefix  awalan kunci galat ('data.' untuk halaman Filament)
     *
     * @throws ValidationException
     */
    public function validateSender(array $data, string $prefix = ''): void
    {
        $method = $data['delivery_method'] ?? null;
        $errors = [];

        if ($method === 'ekspedisi') {
            if (blank($data['driver_name'] ?? null)) {
                $errors[$prefix . 'driver_name'] = 'Nama driver / ekspedisi wajib diisi untuk pengiriman via ekspedisi.';
            }
        } else {
            $driverId = $data['driver_id'] ?? null;
            $vehicleId = $data['vehicle_id'] ?? null;

            if (blank($driverId)) {
                $errors[$prefix . 'driver_id'] = 'Driver wajib dipilih untuk pengiriman internal. Belum ada driver? Tambahkan di Master Driver atau pilih metode Ekspedisi.';
            } elseif (! Driver::withoutGlobalScopes()->whereKey($driverId)->whereNull('deleted_at')->exists()) {
                $errors[$prefix . 'driver_id'] = 'Driver yang dipilih tidak ditemukan atau sudah dihapus dari master.';
            }

            if (blank($vehicleId)) {
                $errors[$prefix . 'vehicle_id'] = 'Kendaraan wajib dipilih untuk pengiriman internal. Belum ada kendaraan? Tambahkan di Master Kendaraan atau pilih metode Ekspedisi.';
            } elseif (! Vehicle::withoutGlobalScopes()->whereKey($vehicleId)->whereNull('deleted_at')->exists()) {
                $errors[$prefix . 'vehicle_id'] = 'Kendaraan yang dipilih tidak ditemukan atau sudah dihapus dari master.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Master driver/kendaraan yang masih kosong (menurut cakupan cabang pengguna, sama dengan dropdown di form).
     *
     * @return array<int, string>  mis. ['driver', 'kendaraan']
     */
    public function missingFleetMasters(): array
    {
        $missing = [];

        if (! Driver::query()->exists()) {
            $missing[] = 'driver';
        }

        if (! Vehicle::query()->exists()) {
            $missing[] = 'kendaraan';
        }

        return $missing;
    }

    /** Default metode: Ekspedisi bila armada internal belum terdaftar, selain itu Internal. */
    public function defaultDeliveryMethod(): string
    {
        return $this->missingFleetMasters() === [] ? 'internal' : 'ekspedisi';
    }

    /**
     * K3: When delivery schedule starts shipping, move related DOs into the reservation-release stage.
     */
    public function startRelatedDeliveryOrders(DeliverySchedule $schedule): int
    {
        if (DeliveryOrderTransitions::enabled()) {
            return $this->startStrict($schedule);
        }

        $deliveryOrders = $schedule->relatedDeliveryOrders();

        $startedCount = 0;

        foreach ($deliveryOrders as $deliveryOrder) {
            if (! $deliveryOrder instanceof DeliveryOrder) {
                continue;
            }

            if (in_array($deliveryOrder->status, ['sent', 'received', 'completed', 'closed'])) {
                continue;
            }

            try {
                $deliveryOrder->update(['status' => 'sent']);
                $startedCount++;
            } catch (\Throwable $e) {
                Log::warning('DeliveryScheduleService: failed to start related DO', [
                    'delivery_schedule_id' => $schedule->id,
                    'delivery_order_id' => $deliveryOrder->id,
                    'do_number' => $deliveryOrder->do_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $startedCount;
    }

    /**
     * K3: When delivery schedule is delivered, mark all related DOs to completed.
     *
     * Transition strategy:
     * - approved/request_approve/draft/request_stock => sent => completed
     * - sent/received/partial => completed
     */
    public function completeRelatedDeliveryOrders(DeliverySchedule $schedule): int
    {
        if (DeliveryOrderTransitions::enabled()) {
            return $this->completeStrict($schedule);
        }

        $deliveryOrders = $schedule->relatedDeliveryOrders();

        $completedCount = 0;

        foreach ($deliveryOrders as $deliveryOrder) {
            if (!$deliveryOrder instanceof DeliveryOrder) {
                continue;
            }

            if (in_array($deliveryOrder->status, ['completed', 'closed'])) {
                continue;
            }

            try {
                if (in_array($deliveryOrder->status, ['approved', 'request_approve', 'draft', 'request_stock'])) {
                    $deliveryOrder->update(['status' => 'sent']);
                    $deliveryOrder->refresh();
                }

                if (in_array($deliveryOrder->status, ['sent', 'received', 'partial', 'approved', 'request_stock'])) {
                    $deliveryOrder->update(['status' => 'completed']);
                    $completedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('DeliveryScheduleService: failed to complete related DO', [
                    'delivery_schedule_id' => $schedule->id,
                    'delivery_order_id' => $deliveryOrder->id,
                    'do_number' => $deliveryOrder->do_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $completedCount;
    }

    // ------------------------------------------------------------------------------------------------
    // T2.3 — alur ketat (flag stock.strict_dispatch): semua lewat DeliveryOrderTransitions, galat TIDAK ditelan.
    // ------------------------------------------------------------------------------------------------

    /** Status jadwal yang berarti barang sudah/sedang di jalan. */
    private const STARTED_STATUSES = ['on_the_way', 'partial_delivered', 'delivered'];

    /** Status DO yang tidak memblokir "Mulai": sudah berangkat atau sudah final. */
    private const NOT_BLOCKING_AT_START = ['sent', 'received', 'completed', 'closed'];

    /** Status DO yang siap dikirim (D4 "Siap Kirim"), termasuk yang dijadwalkan ulang setelah gagal. */
    private const SHIPPABLE = ['approved', 'confirmed', 'partial', 'delivery_failed'];

    /**
     * Penjaga perubahan status jadwal (dipanggil model, sehingga tombol maupun form status sama-sama tercakup).
     *
     * @throws DeliveryOrderTransitionException
     */
    public function guardStatusChange(DeliverySchedule $schedule, string $from, string $to): void
    {
        if (! DeliveryOrderTransitions::enabled() || $from === $to) {
            return;
        }

        // D5: "Tandai Selesai" hanya dari Dalam Perjalanan; Mulai Pengiriman dulu.
        if ($to === 'delivered' && ! in_array($from, ['on_the_way', 'partial_delivered'], true)) {
            throw new DeliveryOrderTransitionException('Jadwal ' . $schedule->schedule_number . ' belum dimulai. Klik "Mulai Pengiriman" lebih dulu (stok baru keluar gudang saat itu), lalu "Tandai Selesai" setelah barang sampai.');
        }

        if (! in_array($from, self::STARTED_STATUSES, true) && in_array($to, ['on_the_way', 'partial_delivered'], true)) {
            $this->assertCanStart($schedule);
        }
    }

    /** Bentuk validasi form: galat penjaga status → ValidationException pada kolom status (Filament menampilkannya di bawah kolom). */
    public function validateStatusChange(DeliverySchedule $schedule, string $to, string $prefix = ''): void
    {
        try {
            $this->guardStatusChange($schedule, (string) $schedule->status, $to);
        } catch (DeliveryOrderTransitionException $e) {
            throw ValidationException::withMessages([$prefix . 'status' => $e->getMessage()]);
        }
    }

    /**
     * "Mulai Pengiriman" hanya bila setiap DO terkait sudah Siap Kirim dan stok fisiknya cukup (D15) — dicek sebelum status jadwal berubah.
     *
     * @throws DeliveryOrderTransitionException
     */
    public function assertCanStart(DeliverySchedule $schedule): void
    {
        $shipments = app(DeliveryShipments::class);

        foreach ($schedule->relatedDeliveryOrders() as $deliveryOrder) {
            $deliveryOrder->refresh();

            if (in_array($deliveryOrder->status, self::NOT_BLOCKING_AT_START, true)) {
                continue;
            }

            if (! in_array($deliveryOrder->status, self::SHIPPABLE, true)) {
                throw new DeliveryOrderTransitionException(sprintf(
                    'Pengiriman belum dapat dimulai: Delivery Order %s masih berstatus "%s". Selesaikan konfirmasi/persetujuan gudang sampai "Siap Kirim", atau keluarkan DO dari jadwal ini.',
                    $deliveryOrder->do_number,
                    DeliveryOrder::statusLabel($deliveryOrder->status)
                ));
            }

            $shortages = $shipments->physicalShortages($deliveryOrder);
            if ($shortages !== []) {
                throw new DeliveryOrderTransitionException(sprintf(
                    'Pengiriman belum dapat dimulai: Delivery Order %s — stok fisik tidak cukup (%s). Tambah stok atau kurangi kuantitas DO; Owner/Super Admin dapat mengecualikan lewat aksi "Kirim" pada DO.',
                    $deliveryOrder->do_number,
                    DeliveryShipments::describeShortages($shortages)
                ), $shortages);
            }
        }
    }

    private function startStrict(DeliverySchedule $schedule): int
    {
        $transitions = app(DeliveryOrderTransitions::class);
        $started = 0;

        DB::transaction(function () use ($schedule, $transitions, &$started) {
            foreach ($schedule->relatedDeliveryOrders() as $deliveryOrder) {
                $deliveryOrder->refresh();

                if (in_array($deliveryOrder->status, self::NOT_BLOCKING_AT_START, true)) {
                    continue;
                }

                $transitions->to($deliveryOrder, 'sent', ['comments' => "Jadwal {$schedule->schedule_number} dimulai", 'source' => 'delivery_schedule']);
                $started++;
            }
        });

        return $started;
    }

    /**
     * Jadwal Selesai: DO yang sudah Dikirim otomatis melewati "Diterima" (tercatat, D23) lalu "Selesai" (jurnal + invoice).
     */
    private function completeStrict(DeliverySchedule $schedule): int
    {
        $transitions = app(DeliveryOrderTransitions::class);
        $completed = 0;

        DB::transaction(function () use ($schedule, $transitions, &$completed) {
            foreach ($schedule->relatedDeliveryOrders() as $deliveryOrder) {
                $deliveryOrder->refresh();

                if (in_array($deliveryOrder->status, ['completed', 'closed', 'reject'], true)) {
                    continue;
                }

                $options = ['source' => 'delivery_schedule'];

                if (in_array($deliveryOrder->status, self::SHIPPABLE, true)) {
                    $transitions->to($deliveryOrder, 'sent', $options + ['comments' => "Jadwal {$schedule->schedule_number} selesai"]);
                }

                $transitions->complete($deliveryOrder, $options + ['comments' => "Jadwal {$schedule->schedule_number} ditandai selesai"]);
                $completed++;
            }
        });

        return $completed;
    }

    /**
     * D20: jadwal ditandai Gagal setelah barang berangkat → DO yang sudah Dikirim menjadi "Pengiriman Gagal" dan stoknya kembali (D18).
     * DO yang belum berangkat tidak berubah.
     */
    public function failRelatedDeliveryOrders(DeliverySchedule $schedule): int
    {
        if (! DeliveryOrderTransitions::enabled()) {
            return 0;
        }

        $reason = trim((string) ($schedule->transitionReason ?? ''));
        $reason = "Jadwal {$schedule->schedule_number} ditandai gagal" . ($reason !== '' ? ": {$reason}" : '');

        $transitions = app(DeliveryOrderTransitions::class);
        $failed = 0;

        DB::transaction(function () use ($schedule, $transitions, $reason, &$failed) {
            foreach ($schedule->relatedDeliveryOrders() as $deliveryOrder) {
                $deliveryOrder->refresh();

                if ($deliveryOrder->status !== 'sent') {
                    continue;
                }

                $transitions->to($deliveryOrder, 'delivery_failed', ['reason' => $reason, 'source' => 'delivery_schedule']);
                $failed++;
            }
        });

        return $failed;
    }
}
