<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrderLog extends Model
{
    use SoftDeletes;
    protected $table = 'delivery_order_logs';
    protected $fillable = [
        'delivery_order_id',
        'status',
        'confirmed_by'
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withDefault();
    }
}
