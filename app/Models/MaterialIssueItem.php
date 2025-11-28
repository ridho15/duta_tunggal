<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialIssueItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $fillable = [
        'material_issue_id',
        'product_id',
        'uom_id',
        'warehouse_id',
        'rak_id',
        'quantity',
        'cost_per_unit',
        'total_cost',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'inventory_coa_id',
    ];

    // Status constants for granular approval workflow
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_COMPLETED = 'completed';

    protected $casts = [
        'quantity' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function materialIssue()
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id')->withDefault();
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

    public function inventoryCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_coa_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    /**
     * Check if the material issue item is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the material issue item is pending approval
     */
    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if the material issue item is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the material issue item is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get the total cost accessor
     */
    public function getTotalCostAttribute(): float
    {
        return $this->quantity * $this->cost_per_unit;
    }
}
