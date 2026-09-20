<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliverySchedule extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    public const STATUS_LABELS = [
        'pending' => 'Menunggu Keberangkatan',
        'on_the_way' => 'Sedang Berjalan',
        'delivered' => 'Selesai / Terkirim',
        'partial_delivered' => 'Sebagian Terkirim',
        'failed' => 'Gagal',
        'cancelled' => 'Dibatalkan',
    ];

    public const STATUS_COLORS = [
        'pending' => 'warning',
        'on_the_way' => 'info',
        'delivered' => 'success',
        'partial_delivered' => 'primary',
        'failed' => 'danger',
        'cancelled' => 'gray',
    ];

    public const METHOD_LABELS = [
        'internal' => 'Internal (Driver Perusahaan)',
        'kurir_internal' => 'Kurir Internal',
        'ekspedisi' => 'Ekspedisi / Pihak Ketiga',
    ];

    /** Metode yang memakai driver & kendaraan dari master. Data lama tanpa metode dianggap internal. */
    public const INTERNAL_METHODS = ['internal', 'kurir_internal'];

    protected $table = 'delivery_schedules';

    protected $fillable = [
        'schedule_number',
        'scheduled_date',
        'delivery_method',
        'driver_id',
        'vehicle_id',
        'driver_name',
        'vehicle_info',
        'tracking_number',
        'status', // pending, on_the_way, delivered, partial_delivered, failed, cancelled
        'notes',
        'created_by',
        'cabang_id',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id')->withDefault();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id')->withDefault();
    }

    public function deliveryOrders()
    {
        return $this->belongsToMany(
            DeliveryOrder::class,
            'delivery_schedule_delivery_orders',
            'delivery_schedule_id',
            'delivery_order_id'
        )->withTimestamps();
    }

    public function suratJalan()
    {
        return $this->belongsToMany(
            SuratJalan::class,
            'delivery_schedule_surat_jalans',
            'delivery_schedule_id',
            'surat_jalan_id'
        )->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function relatedDeliveryOrders(): Collection
    {
        $this->loadMissing('deliveryOrders', 'suratJalan.deliveryOrder');

        $directDeliveryOrders = $this->deliveryOrders instanceof Collection
            ? $this->deliveryOrders
            : $this->deliveryOrders()->get();

        $suratJalanDeliveryOrders = $this->suratJalan
            ->flatMap(function (SuratJalan $suratJalan) {
                $deliveryOrders = $suratJalan->getRelationValue('deliveryOrder');

                return $deliveryOrders instanceof Collection
                    ? $deliveryOrders
                    : $suratJalan->deliveryOrder()->get();
            });

        return $directDeliveryOrders
            ->concat($suratJalanDeliveryOrders)
            ->unique('id')
            ->values();
    }

    public function relatedSuratJalan(): Collection
    {
        $this->loadMissing('suratJalan');

        return $this->suratJalan
            ->unique('id')
            ->values();
    }

    public function relatedSuratJalanSummary(): string
    {
        return $this->relatedSuratJalan()->pluck('sj_number')->implode(', ') ?: '-';
    }

    public function relatedDeliveryOrderSummary(): string
    {
        return $this->relatedDeliveryOrders()->pluck('do_number')->implode(', ') ?: '-';
    }

    public function relatedSuratJalanCount(): int
    {
        return $this->relatedSuratJalan()->count();
    }

    public function relatedDeliveryOrderCount(): int
    {
        return $this->relatedDeliveryOrders()->count();
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? ($status ? ucfirst(str_replace('_', ' ', $status)) : '-');
    }

    public static function statusColor(?string $status): string
    {
        return self::STATUS_COLORS[$status ?? ''] ?? 'gray';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }

    public function getDeliveryMethodLabelAttribute(): string
    {
        return self::METHOD_LABELS[$this->delivery_method ?? '']
            ?? ($this->delivery_method ? ucfirst(str_replace('_', ' ', $this->delivery_method)) : '-');
    }

    public function usesInternalFleet(): bool
    {
        return in_array($this->delivery_method, [...self::INTERNAL_METHODS, null, ''], true);
    }

    /** Nama driver (internal) atau nama driver/ekspedisi (pihak ketiga); '-' bila belum diketahui. */
    public function senderName(): string
    {
        if ($this->delivery_method === 'ekspedisi') {
            return $this->driver_name ?: $this->vehicle_info ?: '-';
        }

        return $this->driver?->name ?: $this->driver_name ?: '-';
    }

    /** "B 1234 ABC (Truck)" untuk armada internal; info kendaraan bebas untuk ekspedisi; '-' bila kosong. */
    public function vehicleLabel(): string
    {
        $plate = $this->vehicle?->plate;
        if ($plate) {
            return $this->vehicle->type ? "{$plate} ({$this->vehicle->type})" : $plate;
        }

        return $this->vehicle_info ?: '-';
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }
}
