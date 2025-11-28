<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $table = 'stock_adjustment_items';

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'rak_id',
        'current_qty',
        'adjusted_qty',
        'difference_qty',
        'unit_cost',
        'difference_value',
        'notes',
    ];

    protected $casts = [
        'current_qty' => 'decimal:2',
        'adjusted_qty' => 'decimal:2',
        'difference_qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'difference_value' => 'decimal:2',
    ];

    public function stockAdjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }
}
