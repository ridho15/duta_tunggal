<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingOrderMaterial extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'manufacturing_order_materials';
    protected $fillable = [
        'manufacturing_order_id',
        'material_id',
        'qty_required',
        'qty_used',
        'warehouse_id'
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id')->withDefault();
    }

    public function warehouse(){
        return $this->belongsTo(Warehouse::class,'warehouse_id')->withDefault();
    }
}
