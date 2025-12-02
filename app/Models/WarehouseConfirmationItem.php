<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseConfirmationItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'warehouse_confirmation_items';
    protected $fillable = [
        'warehouse_confirmation_id',
        'sale_order_item_id',
        'product_name',
        'requested_qty',
        'confirmed_qty',
        'warehouse_id',
        'rak_id',
        'status'
    ];

    public function warehouseConfirmation()
    {
        return $this->belongsTo(WarehouseConfirmation::class)->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class)->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class)->withDefault();
    }

    protected static function booted()
    {
        static::updating(function ($warehouseConfirmationItem) {
            // When item status changes, check if all items in the warehouse confirmation are confirmed
            if ($warehouseConfirmationItem->isDirty('status')) {
                $warehouseConfirmation = $warehouseConfirmationItem->warehouseConfirmation;

                if ($warehouseConfirmation) {
                    // Check if all items are confirmed (including the current item being updated)
                    $totalItems = $warehouseConfirmation->warehouseConfirmationItems()->count();
                    $confirmedItems = $warehouseConfirmation->warehouseConfirmationItems()
                        ->where('status', 'confirmed')
                        ->where('id', '!=', $warehouseConfirmationItem->id) // Exclude current item
                        ->count();

                    // Add 1 if the current item is being set to confirmed
                    if ($warehouseConfirmationItem->status === 'confirmed') {
                        $confirmedItems++;
                    }

                    $allConfirmed = $confirmedItems === $totalItems;

                    // Update parent status based on item statuses
                    if ($allConfirmed) {
                        $warehouseConfirmation->update([
                            'status' => 'Confirmed', // Use the correct enum value
                            'confirmed_by' => \Illuminate\Support\Facades\Auth::id(),
                            'confirmed_at' => now()
                        ]);
                    } else {
                        // If not all items are confirmed, keep status as 'Request'
                        $warehouseConfirmation->update([
                            'status' => 'Request'
                        ]);
                    }
                }
            }
        });
    }
}