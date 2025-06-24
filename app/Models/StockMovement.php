<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'stock_movements';
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'type', //purchase, sales, transfer_in, transfer_out, manufacture_in, manufacture_out, adjustment
        'reference_id',
        'date',
        'notes',
        'rak_id',
        'from_model_type',
        'from_model_id' // nullable
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }
}
