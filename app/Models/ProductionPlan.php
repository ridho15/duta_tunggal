<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class ProductionPlan extends Model
{
    // use SoftDeletes, HasFactory, LogsGlobalActivity;
    use SoftDeletes, HasFactory;

    protected $table = 'production_plans';

    protected $fillable = [
        'plan_number',
        'name',
        'source_type',
        'sale_order_id',
        'bill_of_material_id',
        'product_id',
        'quantity',
        'uom_id',
        'warehouse_id',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'quantity' => 'decimal:2'
    ];

    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id')->withDefault();
    }

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_material_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function manufacturingOrders()
    {
        return $this->hasMany(ManufacturingOrder::class, 'production_plan_id');
    }

    public function materialIssues()
    {
        return $this->hasMany(MaterialIssue::class, 'production_plan_id');
    }

    /**
     * Get material requirements from BOM
     */
    public function getMaterialRequirements()
    {
        try {
            // Eager load billOfMaterial.items.product.uom and inventoryStock if not loaded
            if (!$this->relationLoaded('billOfMaterial')) {
                $this->load(['billOfMaterial.items.product.uom', 'billOfMaterial.items.product.inventoryStock']);
            }
            if (!$this->billOfMaterial) {
                return collect();
            }

            // Pre-load all material issues for this production plan to avoid N+1 queries
            $materialIssues = \App\Models\MaterialIssue::where('production_plan_id', $this->id)
                ->where('type', 'issue')
                ->where('status', 'completed')
                ->with('items')
                ->get()
                ->keyBy('id');

            $requirements = $this->billOfMaterial->items->map(function ($item) use ($materialIssues) {
                $requiredQuantity = $item->quantity * $this->quantity;
                $product = $item->product;

                // Get current stock
                $currentStock = $product->inventoryStock->sum('qty_available') ?? 0;

                // Calculate issued quantity from pre-loaded material issues
                $issuedQuantity = $materialIssues->flatMap(function ($issue) use ($item) {
                    return $issue->items->where('product_id', $item->product_id);
                })->sum('quantity');

                $availabilityStatus = $this->getAvailabilityStatus($requiredQuantity, $currentStock);
                $usageStatus = $this->getUsageStatus($requiredQuantity, $issuedQuantity);

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'uom_name' => $item->uom->name,
                    'required_quantity' => $requiredQuantity,
                    'current_stock' => $currentStock,
                    'issued_quantity' => $issuedQuantity,
                    'remaining_to_issue' => max(0, $requiredQuantity - $issuedQuantity),
                    'availability_status' => $availabilityStatus,
                    'usage_status' => $usageStatus,
                    'is_fully_available' => $currentStock >= $requiredQuantity,
                    'is_fully_issued' => $issuedQuantity >= $requiredQuantity,
                    'availability_percentage' => $currentStock > 0 ? min(100, ($currentStock / $requiredQuantity) * 100) : 0,
                    'usage_percentage' => $requiredQuantity > 0 ? min(100, ($issuedQuantity / $requiredQuantity) * 100) : 0,
                ];
            });
            return $requirements;
        } catch (\Exception $e) {
            Log::error('Error in getMaterialRequirements for ProductionPlan ID: ' . $this->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return collect();
        }
    }

    /**
     * Get overall material availability status
     */
    public function getOverallAvailabilityStatus()
    {
        $requirements = $this->getMaterialRequirements();

        if ($requirements->isEmpty()) {
            return 'no_materials';
        }

        $fullyAvailable = $requirements->where('is_fully_available', true)->count();
        $totalMaterials = $requirements->count();

        if ($fullyAvailable === $totalMaterials) {
            return 'fully_available';
        } elseif ($fullyAvailable > 0) {
            return 'partially_available';
        } else {
            return 'not_available';
        }
    }

    /**
     * Get overall material usage status
     */
    public function getOverallUsageStatus()
    {
        $requirements = $this->getMaterialRequirements();

        if ($requirements->isEmpty()) {
            return 'no_materials';
        }

        $fullyIssued = $requirements->where('is_fully_issued', true)->count();
        $totalMaterials = $requirements->count();

        if ($fullyIssued === $totalMaterials) {
            return 'fully_issued';
        } elseif ($fullyIssued > 0) {
            return 'partially_issued';
        } else {
            return 'not_issued';
        }
    }

    /**
     * Get availability status for a single material
     */
    private function getAvailabilityStatus($required, $available)
    {
        if ($available >= $required) {
            return 'available';
        } elseif ($available > 0) {
            return 'partial';
        } else {
            return 'unavailable';
        }
    }

    /**
     * Get usage status for a single material
     */
    private function getUsageStatus($required, $issued)
    {
        if ($issued >= $required) {
            return 'issued';
        } elseif ($issued > 0) {
            return 'partial';
        } else {
            return 'not_issued';
        }
    }

    /**
     * Check if production can start (all materials available)
     */
    public function canStartProduction()
    {
        return $this->getOverallAvailabilityStatus() === 'fully_available';
    }

    /**
     * Get material fulfillment summary
     */
    public function getFulfillmentSummary()
    {
        try {
            $requirements = $this->getMaterialRequirements();
            $summary = [
                'total_materials' => $requirements->count(),
                'fully_available' => $requirements->where('is_fully_available', true)->count(),
                'partially_available' => $requirements->where('availability_status', 'partial')->count(),
                'not_available' => $requirements->where('availability_status', 'unavailable')->count(),
                'fully_issued' => $requirements->where('is_fully_issued', true)->count(),
                'partially_issued' => $requirements->where('usage_status', 'partial')->count(),
                'not_issued' => $requirements->where('usage_status', 'not_issued')->count(),
                'overall_availability' => $this->getOverallAvailabilityStatus(),
                'overall_usage' => $this->getOverallUsageStatus(),
                'can_start_production' => $this->canStartProduction(),
            ];
            return $summary;
        } catch (\Exception $e) {
            Log::error('Error in getFulfillmentSummary for ProductionPlan ID: ' . $this->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'total_materials' => 0,
                'fully_available' => 0,
                'partially_available' => 0,
                'not_available' => 0,
                'fully_issued' => 0,
                'partially_issued' => 0,
                'not_issued' => 0,
                'overall_availability' => 'error',
                'overall_usage' => 'error',
                'can_start_production' => false,
            ];
        }
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-create MaterialIssue when status changes to scheduled
        static::updating(function ($productionPlan) {
            // Check if status is being changed to 'scheduled'
            if ($productionPlan->isDirty('status') && $productionPlan->status === 'scheduled') {
                // Only create if it doesn't already exist
                $existingIssue = \App\Models\MaterialIssue::where('production_plan_id', $productionPlan->id)
                    ->where('type', 'issue')
                    ->first();

                if (!$existingIssue) {
                    $manufacturingService = app(\App\Services\ManufacturingService::class);
                    try {
                        $materialIssue = $manufacturingService->createMaterialIssueForProductionPlan($productionPlan);

                        if ($materialIssue) {
                            Log::info("MaterialIssue {$materialIssue->issue_number} auto-created for ProductionPlan {$productionPlan->id}");
                        } else {
                            Log::warning("Failed to auto-create MaterialIssue for ProductionPlan {$productionPlan->id}");
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to auto-create MaterialIssue for ProductionPlan {$productionPlan->id}: " . $e->getMessage());
                        // Do not prevent status change, just log the error
                    }
                }
            }
        });

        static::deleting(function ($productionPlan) {
            // Cascade delete related material issues and their items
            $productionPlan->materialIssues()->each(function ($materialIssue) {
                $materialIssue->items()->delete();
                $materialIssue->delete();
            });

            // Cascade delete related manufacturing orders if needed
            $productionPlan->manufacturingOrders()->delete();

        });
    }
}
