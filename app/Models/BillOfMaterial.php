<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterial extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'bill_of_materials';
    protected $fillable = [
        'cabang_id',
        'product_id',
        'quantity',
        'code',
        'nama_bom',
        'note',
        'is_active',
        'uom_id',
        'labor_cost',
        'overhead_cost',
        'total_cost',
        'finished_goods_coa_id',
        'work_in_progress_coa_id',
    ];

    protected $casts = [
        'labor_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::deleting(function ($bom) {
            // Cascade soft delete to items
            $bom->items()->delete();
        });
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }

    public function items()
    {
        return $this->hasMany(BillOfMaterialItem::class, 'bill_of_material_id');
    }

    public function productionPlans()
    {
        return $this->hasMany(ProductionPlan::class, 'bill_of_material_id');
    }

    /**
     * Get active production plans using this BOM
     */
    public function getActiveProductionPlans()
    {
        return $this->productionPlans()
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->get();
    }

    /**
     * Get total quantity planned using this BOM
     */
    public function getTotalPlannedQuantity()
    {
        return $this->productionPlans()
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->sum('quantity');
    }

    /**
     * Check if BOM is currently in use by active production plans
     */
    public function isInUse()
    {
        return $this->productionPlans()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();
    }

    public function finishedGoodsCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'finished_goods_coa_id');
    }

    public function workInProgressCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'work_in_progress_coa_id');
    }

    /**
     * Calculate total cost of BOM (Material + Labor + Overhead)
     */
    public function calculateTotalCost(): float
    {
        $materialCost = $this->items()
            ->with('product')
            ->get()
            ->sum(function ($item) {
                // Prefer explicit BOM item unit_price if defined (supports per‑BOM overrides)
                $unitPrice = $item->unit_price ?? $item->product->cost_price ?? 0;
                return (float) $item->quantity * (float) $unitPrice;
            });

        return $materialCost + $this->labor_cost + $this->overhead_cost;
    }

    /**
     * Update the total_cost field
     */
    public function updateTotalCost(): void
    {
        $this->total_cost = $this->calculateTotalCost();
        $this->save();
    }
}
