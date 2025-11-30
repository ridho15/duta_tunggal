<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseConfirmation extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'warehouse_confirmations';
    protected $fillable = [
        'sale_order_id',
        'manufacturing_order_id',
        'note',
        'status', // Confirmed / Rejected / Request
        'confirmed_by',
        'confirmed_at'
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id')->withDefault();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withDefault();
    }

    public function warehouseConfirmationItems()
    {
        return $this->hasMany(WarehouseConfirmationItem::class);
    }

    protected static function booted()
    {
        static::deleting(function ($warehouseConfirmation) {
            if ($warehouseConfirmation->isForceDeleting()) {
                $warehouseConfirmation->warehouseConfirmationItems()->forceDelete();
            } else {
                $warehouseConfirmation->warehouseConfirmationItems()->delete();
            }
        });

        static::restoring(function ($warehouseConfirmation) {
            $warehouseConfirmation->warehouseConfirmationItems()->withTrashed()->restore();
        });
    }
}
