<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'vehicles';
    protected $fillable = [
        'plate',
        'type',
        'capacity',
        'cabang_id'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function deliveryOrder()
    {
        return $this->hasMany(DeliveryOrder::class, 'vehicle_id');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
