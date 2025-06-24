<?php

namespace App\Models;

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
        'license'
    ];

    public function deliveryOrder()
    {
        return $this->hasMany(DeliveryOrder::class, 'driver_id');
    }
}
