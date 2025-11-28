<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseConfirmationItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'warehouse_confirmation_items';
    protected $fillable = [
        'warehouse_confirmation_id',
        'sale_order_item_id',
        'confirmed_qty',
        'warehouse_id',
        'rak_id',
        'status'
    ];

    public function warehouseConfirmation()
    {
        return $this->belongsTo(WarehouseConfirmation::class)->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class)->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class)->withDefault();
    }
}