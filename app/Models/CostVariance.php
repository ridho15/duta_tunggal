<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostVariance extends Model
{
    protected $fillable = [
        'production_cost_entry_id',
        'variance_type',
        'standard_cost',
        'actual_cost',
        'variance_amount',
        'variance_percentage',
        'notes',
    ];

    protected $casts = [
        'standard_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'variance_percentage' => 'decimal:4',
    ];

    public function productionCostEntry(): BelongsTo
    {
        return $this->belongsTo(ProductionCostEntry::class);
    }

    /**
     * Get variance type label
     */
    public function getVarianceTypeLabelAttribute(): string
    {
        return match ($this->variance_type) {
            'material' => 'Material Variance',
            'labor' => 'Labor Variance',
            'overhead' => 'Overhead Variance',
            'volume' => 'Volume Variance',
            default => ucfirst($this->variance_type),
        };
    }

    /**
     * Check if variance is favorable (negative = favorable for costs)
     */
    public function getIsFavorableAttribute(): bool
    {
        return $this->variance_amount <= 0;
    }
}
