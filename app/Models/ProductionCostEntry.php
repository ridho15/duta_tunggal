<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionCostEntry extends Model
{
    protected $fillable = [
        'production_id',
        'product_id',
        'quantity_produced',
        'actual_material_cost',
        'actual_labor_cost',
        'actual_overhead_cost',
        'total_actual_cost',
        'production_date',
    ];

    protected $casts = [
        'production_date' => 'date',
        'quantity_produced' => 'integer',
        'actual_material_cost' => 'decimal:2',
        'actual_labor_cost' => 'decimal:2',
        'actual_overhead_cost' => 'decimal:2',
        'total_actual_cost' => 'decimal:2',
    ];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function costVariances(): HasMany
    {
        return $this->hasMany(CostVariance::class);
    }

    /**
     * Calculate cost per unit
     */
    public function getCostPerUnitAttribute(): float
    {
        return $this->quantity_produced > 0 ? $this->total_actual_cost / $this->quantity_produced : 0;
    }

    /**
     * Calculate variances against standard cost
     */
    public function calculateVariances(): void
    {
        $standardCost = ProductStandardCost::getCurrentStandardCost($this->product_id);

        if (!$standardCost) {
            return;
        }

        $standardTotal = $standardCost->total_standard_cost * $this->quantity_produced;

        // Material Variance
        $this->createVariance('material', $standardCost->standard_material_cost * $this->quantity_produced, $this->actual_material_cost);

        // Labor Variance
        $this->createVariance('labor', $standardCost->standard_labor_cost * $this->quantity_produced, $this->actual_labor_cost);

        // Overhead Variance
        $this->createVariance('overhead', $standardCost->standard_overhead_cost * $this->quantity_produced, $this->actual_overhead_cost);

        // Volume Variance (total variance)
        $this->createVariance('volume', $standardTotal, $this->total_actual_cost);
    }

    private function createVariance(string $type, float $standard, float $actual): void
    {
        $variance = $actual - $standard;
        $percentage = $standard > 0 ? ($variance / $standard) * 100 : 0;

        CostVariance::create([
            'production_cost_entry_id' => $this->id,
            'variance_type' => $type,
            'standard_cost' => $standard,
            'actual_cost' => $actual,
            'variance_amount' => $variance,
            'variance_percentage' => $percentage,
        ]);
    }
}
