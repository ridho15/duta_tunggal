<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'vehicles';
    protected $fillable = [
        'plate',
        'type',
        'capacity'
    ];

    public function deliveryOrder()
    {
        return $this->hasMany(DeliveryOrder::class, 'vehicle_id');
    }
}
