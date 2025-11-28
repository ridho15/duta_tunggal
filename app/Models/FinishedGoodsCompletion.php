<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinishedGoodsCompletion extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity;

    protected $fillable = [
        'completion_number',
        'production_plan_id',
        'product_id',
        'quantity',
        'uom_id',
        'total_cost',
        'completion_date',
        'warehouse_id',
        'rak_id',
        'notes',
        'status',
        'completed_by',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'completion_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Calculate total WIP cost from material issues
     */
    public function calculateWipCost(): float
    {
        if (!$this->productionPlan) {
            return 0;
        }

        // Get all material issues for this production plan
        $materialIssues = MaterialIssue::where('production_plan_id', $this->productionPlan->id)
            ->where('type', 'issue')
            ->where('status', 'completed')
            ->with('items')
            ->get();

        $totalCost = 0;
        foreach ($materialIssues as $issue) {
            foreach ($issue->items as $item) {
                $totalCost += $item->total_cost ?? 0;
            }
        }

        return $totalCost;
    }
}
