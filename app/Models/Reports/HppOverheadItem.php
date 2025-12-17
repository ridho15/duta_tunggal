<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HppOverheadItem extends Model
{
    protected $table = 'report_hpp_overhead_items';

    protected $fillable = [
        'key',
        'label',
        'sort_order',
        'allocation_basis',
        'allocation_rate',
    ];

    protected $casts = [
        'allocation_rate' => 'decimal:4',
    ];

    public function prefixes(): HasMany
    {
        return $this->hasMany(HppOverheadItemPrefix::class, 'overhead_item_id');
    }

    /**
     * Calculate allocated overhead amount based on allocation basis
     */
    public function calculateAllocatedAmount(float $directLabor = 0, float $machineHours = 0, float $directMaterial = 0, int $productionVolume = 0): float
    {
        $baseAmount = match ($this->allocation_basis) {
            'direct_labor' => $directLabor,
            'machine_hours' => $machineHours,
            'direct_material' => $directMaterial,
            'production_volume' => $productionVolume,
            'fixed_amount' => 1, // Fixed amount per allocation rate
            default => $directLabor,
        };

        return $baseAmount * $this->allocation_rate;
    }

    /**
     * Get allocation basis label
     */
    public function getAllocationBasisLabelAttribute(): string
    {
        return match ($this->allocation_basis) {
            'direct_labor' => 'Direct Labor Cost',
            'machine_hours' => 'Machine Hours',
            'direct_material' => 'Direct Material Cost',
            'production_volume' => 'Production Volume',
            'fixed_amount' => 'Fixed Amount',
            default => 'Direct Labor Cost',
        };
    }
}
