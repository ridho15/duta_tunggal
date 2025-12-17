<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStandardCost extends Model
{
    protected $fillable = [
        'product_id',
        'standard_material_cost',
        'standard_labor_cost',
        'standard_overhead_cost',
        'total_standard_cost',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'standard_material_cost' => 'decimal:2',
        'standard_labor_cost' => 'decimal:2',
        'standard_overhead_cost' => 'decimal:2',
        'total_standard_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the current standard cost for a product
     */
    public static function getCurrentStandardCost(int $productId): ?self
    {
        return static::where('product_id', $productId)
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();
    }
}
