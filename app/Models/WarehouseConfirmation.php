<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        static::updating(function ($warehouseConfirmation) {
            // When parent status changes to confirmed, update all warehouse confirmation items
            if ($warehouseConfirmation->isDirty('status') && $warehouseConfirmation->status === 'confirmed') {
                // Update direct warehouse confirmation items
                $warehouseConfirmation->warehouseConfirmationItems()->update([
                    'status' => 'confirmed',
                    'confirmed_qty' => DB::raw('requested_qty'),
                ]);
            }
        });

        static::deleting(function ($warehouseConfirmation) {
            // Update sale order status back to request_approve when warehouse confirmation is deleted
            // This requires re-approval process since warehouse confirmation was cancelled
            if ($warehouseConfirmation->saleOrder) {
                $warehouseConfirmation->saleOrder->update([
                    'status' => 'request_approve',
                    'warehouse_confirmed_at' => null
                ]);
                
                Log::info('Sale Order status updated to request_approve after warehouse confirmation deletion', [
                    'sale_order_id' => $warehouseConfirmation->sale_order_id,
                    'warehouse_confirmation_id' => $warehouseConfirmation->id
                ]);
            }

            if ($warehouseConfirmation->isForceDeleting()) {
                $warehouseConfirmation->warehouseConfirmationItems()->forceDelete();
            } else {
                $warehouseConfirmation->warehouseConfirmationItems()->delete();
            }
        });

        static::restoring(function ($warehouseConfirmation) {
            // When warehouse confirmation is restored, we might need to update sale order status
            // But this depends on the business logic - for now, we'll leave it as is
            // since the sale order status should be managed separately
            
            $warehouseConfirmation->warehouseConfirmationItems()->withTrashed()->restore();
        });
    }
}
