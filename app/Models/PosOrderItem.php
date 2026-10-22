<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrderItem extends Model
{
    use HasFactory;

    protected $table = 'pos_order_items';

    protected $fillable = [
        'pos_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'harga_batas',
        'cost_price',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'harga_batas' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    public function posOrder()
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    /**
     * Net selling price per unit after line discount
     */
    public function getEffectiveUnitPriceAttribute(): float
    {
        $qty = (float) $this->quantity;
        if ($qty <= 0) {
            return (float) $this->unit_price;
        }

        return max(0.0, ((float) $this->subtotal) / $qty);
    }
}
