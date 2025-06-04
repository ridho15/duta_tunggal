<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'stock_movements';
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'type', //purchase, sales, transfer_in, transfer_out, manufacture_in, manufacture_out, adjustment
        'reference_id',
        'date',
        'notes'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }
}
