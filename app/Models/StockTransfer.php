<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Testing\Fluent\Concerns\Has;

class StockTransfer extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'stock_transfers';
    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status' //Pending, completed, cancelled, Draft, Approved, Request, Reject
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withDefault();
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withDefault();
    }

    public function stockTransferItem()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    protected static function booted()
    {
        static::deleting(function ($stockTransfer) {
            // Delete related StockMovement records and adjust inventory
            foreach ($stockTransfer->stockTransferItem as $item) {
                // Delete StockMovement records
                $item->stockMovement()->delete();
                
                // Note: Inventory adjustments should be handled by StockMovement observer
                // when StockMovement records are deleted
            }
            
            if ($stockTransfer->isForceDeleting()) {
                $stockTransfer->stockTransferItem()->forceDelete();
            } else {
                $stockTransfer->stockTransferItem()->delete();
            }
        });

        static::restoring(function ($stockTransfer) {
            $stockTransfer->stockTransferItem()->withTrashed()->restore();
        });

        // Handle updates to StockTransfer (when quantity changes)
        static::updating(function ($stockTransfer) {
            // Only handle if status is approved and there are changes to items
            if ($stockTransfer->status === 'Approved' && $stockTransfer->isDirty()) {
                // Check if any related items have changed
                $changedItems = $stockTransfer->stockTransferItem()->get();
                foreach ($changedItems as $item) {
                    if ($item->isDirty('quantity')) {
                        // Update related StockMovement records
                        $oldQuantity = $item->getOriginal('quantity');
                        $newQuantity = $item->quantity;
                        
                        // Update transfer_out movement
                        $transferOut = $item->stockMovement()
                            ->where('type', 'transfer_out')
                            ->where('warehouse_id', $item->from_warehouse_id)
                            ->first();
                        if ($transferOut) {
                            $transferOut->update(['quantity' => $newQuantity]);
                        }
                        
                        // Update transfer_in movement
                        $transferIn = $item->stockMovement()
                            ->where('type', 'transfer_in')
                            ->where('warehouse_id', $item->to_warehouse_id)
                            ->first();
                        if ($transferIn) {
                            $transferIn->update(['quantity' => $newQuantity]);
                        }
                        
                        // Note: Inventory adjustments for quantity changes 
                        // should be handled by StockMovement observer
                    }
                }
            }
        });
    }
}
