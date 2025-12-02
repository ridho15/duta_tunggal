<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class WarehouseConfirmationWarehouse extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'warehouse_confirmation_warehouses';
    protected $fillable = [
        'warehouse_confirmation_id',
        'warehouse_id',
        'status', // request, confirmed, partial_confirmed, rejected
        'confirmed_by',
        'confirmed_at',
        'notes'
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function warehouseConfirmation()
    {
        return $this->belongsTo(WarehouseConfirmation::class)->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withDefault();
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withDefault();
    }

    public function warehouseConfirmationItems()
    {
        return $this->hasMany(WarehouseConfirmationItem::class, 'warehouse_confirmation_warehouse_id');
    }

    protected static function booted()
    {
        static::updating(function ($warehouseConfirmationWarehouse) {
            // Auto-update confirmed_at when status changes to confirmed
            if ($warehouseConfirmationWarehouse->isDirty('status') &&
                in_array($warehouseConfirmationWarehouse->status, ['confirmed', 'partial_confirmed', 'rejected'])) {
                $warehouseConfirmationWarehouse->confirmed_at = now();
                $warehouseConfirmationWarehouse->confirmed_by = Auth::id();
            }

            // When warehouse confirmation status changes to confirmed, update all items
            if ($warehouseConfirmationWarehouse->isDirty('status') && $warehouseConfirmationWarehouse->status === 'confirmed') {
                $warehouseConfirmationWarehouse->warehouseConfirmationItems()->update([
                    'status' => 'confirmed'
                ]);
            }
        });
    }
}