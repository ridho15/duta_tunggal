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

    protected $table = 'delivery_schedules';

    protected $fillable = [
        'schedule_number',
        'scheduled_date',
        'delivery_method',
        'driver_id',
        'vehicle_id',
        'driver_name',
        'vehicle_info',
        'status', // pending, on_the_way, delivered, failed, cancelled
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
        $this->loadMissing('suratJalan.deliveryOrder');

        return $this->suratJalan
            ->flatMap(function (SuratJalan $suratJalan) {
                $deliveryOrders = $suratJalan->getRelationValue('deliveryOrder');

                return $deliveryOrders instanceof Collection
                    ? $deliveryOrders
                    : $suratJalan->deliveryOrder()->get();
            })
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

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'           => 'Menunggu Keberangkatan',
            'on_the_way'        => 'Sedang Berjalan',
            'delivered'         => 'Selesai / Terkirim',
            'failed'            => 'Gagal',
            'cancelled'         => 'Dibatalkan',
            default             => ucfirst($this->status),
        };
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }
}
