<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'stock_adjustments';

    protected $fillable = [
        'adjustment_number',
        'adjustment_date',
        'warehouse_id',
        'adjustment_type',
        'reason',
        'notes',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'fromModel', 'from_model_type', 'from_model_id');
    }

    /**
     * Generate a unique adjustment number
     */
    public static function generateAdjustmentNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'ADJ-' . $date . '-';

        // Find the latest adjustment number for today
        $latest = self::where('adjustment_number', 'like', $prefix . '%')
            ->orderBy('adjustment_number', 'desc')
            ->first();

        if ($latest) {
            // Extract the sequential number and increment
            $lastNumber = (int) substr($latest->adjustment_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format as 3-digit number with leading zeros
        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
