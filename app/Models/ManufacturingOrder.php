<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingOrder extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $guarded = ['id'];
    protected $table = 'manufacturing_orders';
    protected $fillable = [
        'mo_number',
        'production_plan_id',
        'status', // draft, in_progress, completed
        'start_date',
        'end_date',
        'rak_id',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function manufacturingOrderMaterial()
    {
        return $this->hasMany(ManufacturingOrderMaterial::class, 'manufacturing_order_id');
    }

    public function production()
    {
        return $this->hasOne(Production::class, 'manufacturing_order_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function productionPlan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id')->withDefault();
    }

    /**
     * Get material issues for this manufacturing order's production plan
     */
    public function materialIssues()
    {
        return $this->hasManyThrough(MaterialIssue::class, ProductionPlan::class, 'id', 'production_plan_id', 'production_plan_id', 'id');
    }

    /**
     * Get completed material issues for this manufacturing order's production plan
     */
    public function completedMaterialIssues()
    {
        return $this->materialIssues()->where('status', 'completed');
    }

    /**
     * Check if all required materials are fully issued for this manufacturing order
     */
    public function areAllMaterialsIssued(): bool
    {
        $plan = $this->productionPlan;
        if (!$plan || !$plan->billOfMaterial) {
            return false;
        }

        foreach ($plan->billOfMaterial->items as $item) {
            $requiredQuantity = $item->quantity * $plan->quantity;

            // Get issued quantity from completed material issues
            $issuedQuantity = $this->completedMaterialIssues()
                ->whereHas('items', function ($query) use ($item) {
                    $query->where('product_id', $item->product_id);
                })
                ->with('items')
                ->get()
                ->sum(function ($issue) use ($item) {
                    return $issue->items->where('product_id', $item->product_id)->sum('quantity');
                });

            if ($issuedQuantity < $requiredQuantity) {
                return false;
            }
        }

        return true;
    }
}
