<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Services\ManufacturingJournalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialIssue extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $fillable = [
        'issue_number',
        'production_plan_id',
        'manufacturing_order_id',
        'warehouse_id',
        'issue_date',
        'type',
        'status',
        'total_cost',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'wip_coa_id',
        'inventory_coa_id',
    ];

    // Status constants for granular approval workflow
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_COMPLETED = 'completed';

    protected $casts = [
        'issue_date' => 'date',
        'total_cost' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function productionPlan()
    {
        return $this->belongsTo(\App\Models\ProductionPlan::class, 'production_plan_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function items()
    {
        return $this->hasMany(MaterialIssueItem::class, 'material_issue_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function wipCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'wip_coa_id')->withDefault();
    }

    public function inventoryCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_coa_id')->withDefault();
    }

    public function journalEntry()
    {
        return $this->morphOne(JournalEntry::class, 'source');
    }

    /**
     * Calculate total cost from items
     */
    public function calculateTotalCost(): float
    {
        return $this->items()->sum('total_cost');
    }

    /**
     * Update the total_cost field
     */
    public function updateTotalCost(): void
    {
        $this->total_cost = $this->calculateTotalCost();
        $this->save();
    }

    /**
     * Scope for issue type
     */
    public function scopeIssueType($query)
    {
        return $query->where('type', 'issue');
    }

    /**
     * Scope for return type
     */
    public function scopeReturnType($query)
    {
        return $query->where('type', 'return');
    }

    /**
     * Scope for completed status
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for draft status
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for pending approval status
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    /**
     * Scope for approved status
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Check if the material issue is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the material issue is pending approval
     */
    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if the material issue is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the material issue is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Booted model events to auto generate manufacturing journals
     */
    protected static function booted()
    {
        // Handle status transition updates for material fulfillment
        static::updated(function (MaterialIssue $issue) {
            // Only act when status changed from non-completed to completed
            if ($issue->status === 'completed' && $issue->getOriginal('status') !== 'completed') {
                // Refresh material fulfillment snapshot for related production plan
                if ($issue->production_plan_id) {
                    try {
                        app(\App\Services\ManufacturingService::class)
                            ->updateMaterialFulfillment($issue->productionPlan);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to update material fulfillment after issue completion', [
                            'material_issue_id' => $issue->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }

}
