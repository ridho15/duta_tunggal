<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
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
        'driver_id',
        'vehicle_id',
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

    public function suratJalans()
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

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'           => 'Menunggu Keberangkatan',
            'on_the_way'        => 'Sedang Berjalan',
            'delivered'         => 'Selesai / Terkirim',
            'partial_delivered' => 'Sebagian Terkirim',
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
