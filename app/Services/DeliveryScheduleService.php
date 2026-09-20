<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\Vehicle;
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
}
