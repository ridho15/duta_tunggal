<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'drivers';
    protected $fillable = [
        'name',
        'phone',
        'license',
        'cabang_id'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function deliveryOrder()
    {
        return $this->hasMany(DeliveryOrder::class, 'driver_id');
    }

    public function deliverySchedule()
    {
        return $this->hasMany(DeliverySchedule::class, 'driver_id');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if ($this->deliveryOrder()->exists()) {
            $blockers[] = 'memiliki ' . $this->deliveryOrder()->count() . ' riwayat pengantaran surat jalan (Delivery Order)';
        }

        if ($this->deliverySchedule()->exists()) {
            $blockers[] = 'memiliki jadwal pengiriman terjadwal';
        }

        return $blockers;
    }
}
