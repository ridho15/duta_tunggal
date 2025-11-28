<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialFulfillment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'production_plan_id',
        'material_id',
        'uom_id',
        'required_quantity',
        'current_stock',
        'issued_quantity',
        'remaining_to_issue',
        'availability_percentage',
        'usage_percentage',
        'last_updated_at',
    ];

    protected $casts = [
        'required_quantity' => 'decimal:4',
        'current_stock' => 'decimal:4',
        'issued_quantity' => 'decimal:4',
        'remaining_to_issue' => 'decimal:4',
        'availability_percentage' => 'decimal:2',
        'usage_percentage' => 'decimal:2',
        'last_updated_at' => 'datetime',
    ];

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get fulfillment summary for a production plan
     */
    public static function getFulfillmentSummary(ProductionPlan $plan): array
    {
        $fulfillments = self::where('production_plan_id', $plan->id)->get();

        $total = $fulfillments->count();
        $fully_available = $fulfillments->where('availability_percentage', '>=', 100)->count();
        $partially_available = $fulfillments->where('availability_percentage', '>', 0)->where('availability_percentage', '<', 100)->count();
        $not_available = $fulfillments->where('availability_percentage', 0)->count();

        $fully_issued = $fulfillments->where('usage_percentage', '>=', 100)->count();
        $partially_issued = $fulfillments->where('usage_percentage', '>', 0)->where('usage_percentage', '<', 100)->count();
        $not_issued = $fulfillments->where('usage_percentage', 0)->count();

        return [
            'total_materials' => $total,
            'fully_available' => $fully_available,
            'partially_available' => $partially_available,
            'not_available' => $not_available,
            'fully_issued' => $fully_issued,
            'partially_issued' => $partially_issued,
            'not_issued' => $not_issued,
        ];
    }

    /**
     * Check if production can start (all materials available)
     */
    public static function canStartProduction(ProductionPlan $plan): bool
    {
        $fulfillments = self::where('production_plan_id', $plan->id)->get();

        if ($fulfillments->isEmpty()) {
            return false;
        }

        return $fulfillments->every(fn($fulfillment) => $fulfillment->availability_percentage >= 100);
    }

    /**
     * Update fulfillment data for a production plan
     */
    public static function updateFulfillmentData(ProductionPlan $plan): void
    {
        if (!$plan->billOfMaterial) {
            return;
        }

        foreach ($plan->billOfMaterial->items as $item) {
            $requiredQuantity = $item->quantity * $plan->quantity;

            // Get current stock from inventory - sum only positive available quantities across all warehouses
            $currentStock = InventoryStock::where('product_id', $item->product_id)
                ->where('qty_available', '>', 0)
                ->sum('qty_available');

            // Get net issued quantity from material issues (approved issues minus returns)
            $issuedQuantity = (
                MaterialIssueItem::whereHas('materialIssue', function ($query) use ($plan) {
                    $query->where('production_plan_id', $plan->id)
                        ->where('type', 'issue')
                        ->where('approved_by', '!=', null)
                        ->where('approved_at', '!=', null);
                })
                ->where('product_id', $item->product_id)
                ->sum('quantity')
            ) - (
                MaterialIssueItem::whereHas('materialIssue', function ($query) use ($plan) {
                    $query->where('production_plan_id', $plan->id)
                        ->where('type', 'return')
                        ->where('approved_by', '!=', null)
                        ->where('approved_at', '!=', null);
                })
                ->where('product_id', $item->product_id)
                ->sum('quantity')
            );

            $remainingToIssue = max(0, $requiredQuantity - $issuedQuantity);
            $availabilityPercentage = $currentStock >= $requiredQuantity ? 100 : ($currentStock > 0 ? ($currentStock / $requiredQuantity) * 100 : 0);
            $usagePercentage = $issuedQuantity >= $requiredQuantity ? 100 : ($issuedQuantity > 0 ? ($issuedQuantity / max(1e-9, $requiredQuantity)) * 100 : 0);

            self::updateOrCreate(
                [
                    'production_plan_id' => $plan->id,
                    'material_id' => $item->product_id,
                ],
                [
                    'required_quantity' => $requiredQuantity,
                    'current_stock' => $currentStock,
                    'issued_quantity' => $issuedQuantity,
                    'remaining_to_issue' => $remainingToIssue,
                    'availability_percentage' => $availabilityPercentage,
                    'usage_percentage' => $usagePercentage,
                    'uom_id' => $item->uom_id,
                    'last_updated_at' => now(),
                ]
            );
        }
    }
}
