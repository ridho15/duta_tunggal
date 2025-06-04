<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseConfirmation extends Model
{
    use SoftDeletes;
    protected $table = 'warehouse_confirmations';
    protected $fillable = [
        'manufacturing_order_id',
        'note',
        'status',
        'confirmed_by',
        'confirmed_at'
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withDefault();
    }
}
