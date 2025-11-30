<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Services\ManufacturingJournalService;
use App\Services\StockReservationService;
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
            Log::info('MaterialIssue booted() triggered', [
                'id' => $issue->id,
                'status' => $issue->status,
                'original_status' => $issue->getOriginal('status'),
            ]);
            // Handle ProductionPlan status update when MaterialIssue is requested for approval or approved
            if (($issue->status === 'pending_approval' || $issue->status === 'approved') && $issue->getOriginal('status') !== $issue->status) {
                if ($issue->production_plan_id) {
                    try {
                        $productionPlan = $issue->productionPlan;
                        if ($productionPlan && $productionPlan->status === 'scheduled') {
                            $productionPlan->update(['status' => 'in_progress']);
                            
                            Log::info("ProductionPlan {$productionPlan->id} status changed to 'in_progress' due to MaterialIssue {$issue->id} status change to {$issue->status}");
                        }
                    } catch (\Throwable $e) {
                        Log::error('Failed to update ProductionPlan status after MaterialIssue status change', [
                            'material_issue_id' => $issue->id,
                            'production_plan_id' => $issue->production_plan_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Set items to pending approval when status changes to pending_approval
                if ($issue->status === 'pending_approval' && $issue->getOriginal('status') === 'draft') {
                    $issue->items()->where('status', 'draft')
                        ->update(['status' => 'pending_approval']);
                }
            }

            // Handle stock reservations based on status changes
            $stockReservationService = app(StockReservationService::class);

            // Reserve stock when approved
            if ($issue->status === 'approved' && $issue->getOriginal('status') !== 'approved') {
                try {
                    $stockReservationService->reserveStockForMaterialIssue($issue);
                    Log::info("Stock reserved for approved MaterialIssue {$issue->id}");

                    // Set all pending items to approved
                    $issue->items()->where('status', 'pending_approval')
                        ->update([
                            'status' => 'approved',
                            'approved_by' => $issue->approved_by,
                            'approved_at' => $issue->approved_at,
                        ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to reserve stock for MaterialIssue', [
                        'material_issue_id' => $issue->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Release stock reservations when rejected or set back to draft
            if (($issue->status === 'draft' || $issue->status === 'rejected') &&
                in_array($issue->getOriginal('status'), ['pending_approval', 'approved'])) {
                try {
                    $stockReservationService->releaseStockReservationsForMaterialIssue($issue);
                    Log::info("Stock reservations released for MaterialIssue {$issue->id} (status: {$issue->status})");

                    // Set all items back to draft
                    $issue->items()->update(['status' => 'draft']);
                } catch (\Throwable $e) {
                    Log::error('Failed to release stock reservations for MaterialIssue', [
                        'material_issue_id' => $issue->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Consume reserved stock when completed
            if ($issue->status === 'completed' && $issue->getOriginal('status') !== 'completed') {
                try {
                    // $stockReservationService->consumeReservedStockForMaterialIssue($issue); // Moved to MaterialIssueObserver
                    Log::info("Reserved stock consumed for completed MaterialIssue {$issue->id}");

                    // Set all approved items to completed
                    $issue->items()->where('status', 'approved')
                        ->update(['status' => 'completed']);
                } catch (\Throwable $e) {
                    Log::error('Failed to consume reserved stock for MaterialIssue', [
                        'material_issue_id' => $issue->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        // Cascade delete items and journal when material issue is deleted
        static::deleting(function ($materialIssue) {
            $materialIssue->items()->delete();
            $materialIssue->journalEntry()->delete();

            // Delete related stock movements
            try {
                \App\Models\StockMovement::where('from_model_type', MaterialIssue::class)
                    ->where('from_model_id', $materialIssue->id)
                    ->delete();
                Log::info("Stock movements deleted for deleted MaterialIssue {$materialIssue->id}");
            } catch (\Throwable $e) {
                Log::error('Failed to delete stock movements for deleted MaterialIssue', [
                    'material_issue_id' => $materialIssue->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Release and delete stock reservations when material issue is deleted
            try {
                $stockReservationService = app(StockReservationService::class);
                $stockReservationService->releaseStockReservationsForMaterialIssue($materialIssue);
                \App\Models\StockReservation::where('material_issue_id', $materialIssue->id)->delete();
                Log::info("Stock reservations released and deleted for deleted MaterialIssue {$materialIssue->id}");
            } catch (\Throwable $e) {
                Log::error('Failed to release and delete stock reservations for deleted MaterialIssue', [
                    'material_issue_id' => $materialIssue->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Reset Production Plan status to draft when Material Issue is deleted
            if ($materialIssue->production_plan_id) {
                try {
                    $productionPlan = $materialIssue->productionPlan;
                    if ($productionPlan && $productionPlan->status !== 'draft') {
                        $productionPlan->update(['status' => 'draft']);
                        Log::info("ProductionPlan {$productionPlan->id} status reset to draft after MaterialIssue {$materialIssue->id} deletion");
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to reset ProductionPlan status for deleted MaterialIssue', [
                        'material_issue_id' => $materialIssue->id,
                        'production_plan_id' => $materialIssue->production_plan_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

}
