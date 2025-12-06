<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'stock_opnames';

    protected $fillable = [
        'opname_number',
        'opname_date',
        'warehouse_id',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'opname_date' => 'date',
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
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'fromModel', 'from_model_type', 'from_model_id');
    }

    /**
     * Generate a unique opname number
     */
    public static function generateOpnameNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'OPN-' . $date . '-';

        // Find the latest opname number for today
        $latest = self::where('opname_number', 'like', $prefix . '%')
            ->orderBy('opname_number', 'desc')
            ->first();

        if ($latest) {
            // Extract the sequential number and increment
            $lastNumber = (int) substr($latest->opname_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format as 3-digit number with leading zeros
        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
