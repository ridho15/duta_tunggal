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
        'confirmation_type',
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
        static::created(function ($warehouseConfirmation) {
            // Load relationships first
            $warehouseConfirmation->load(['saleOrder', 'warehouseConfirmationItems.saleOrderItem.product']);
            
            // If warehouse confirmation is created with confirmed status, update sale order immediately
            if ($warehouseConfirmation->status === 'confirmed' && $warehouseConfirmation->saleOrder) {
                $warehouseConfirmation->saleOrder->update([
                    'warehouse_confirmed_at' => now()
                ]);

                Log::info('Sale Order warehouse_confirmed_at updated after warehouse confirmation auto-approval', [
                    'sale_order_id' => $warehouseConfirmation->sale_order_id,
                    'warehouse_confirmation_id' => $warehouseConfirmation->id
                ]);

                // Note: Delivery order creation is now handled in SaleOrderObserver after WC items are created
            }
        });

        static::updating(function ($warehouseConfirmation) {
            $originalStatus = $warehouseConfirmation->getOriginal('status');
            $newStatus = $warehouseConfirmation->status;

            // When parent status changes to confirmed, update all warehouse confirmation items
            if ($warehouseConfirmation->isDirty('status') && $warehouseConfirmation->status === 'confirmed') {
                // Update direct warehouse confirmation items
                $warehouseConfirmation->warehouseConfirmationItems()->update([
                    'status' => 'confirmed',
                    'confirmed_qty' => DB::raw('requested_qty'),
                ]);

                // Update sale order warehouse_confirmed_at if this is a sales order confirmation
                if ($warehouseConfirmation->saleOrder) {
                    $warehouseConfirmation->saleOrder->update([
                        'warehouse_confirmed_at' => now()
                    ]);

                    Log::info('Sale Order warehouse_confirmed_at updated after warehouse confirmation approval', [
                        'sale_order_id' => $warehouseConfirmation->sale_order_id,
                        'warehouse_confirmation_id' => $warehouseConfirmation->id
                    ]);
                }

                // Create delivery order automatically for sales order confirmations (after items are updated)
                if ($warehouseConfirmation->saleOrder) {
                    // Refresh the model to get updated relationships
                    $warehouseConfirmation->refresh();
                    $warehouseConfirmation->load('warehouseConfirmationItems.saleOrderItem.product');
                    
                    static::createDeliveryOrderForConfirmedWarehouseConfirmation($warehouseConfirmation);
                }
            }

            // When status changes from confirmed to request, delete associated delivery order
            if (strtolower($originalStatus) === 'confirmed' && strtolower($newStatus) === 'request' && $warehouseConfirmation->saleOrder) {
                $existingDO = $warehouseConfirmation->saleOrder->deliveryOrder()->first();
                
                if ($existingDO) {
                    $existingDO->delete();
                    
                    Log::info('Delivery Order deleted due to warehouse confirmation status change to request', [
                        'delivery_order_id' => $existingDO->id,
                        'warehouse_confirmation_id' => $warehouseConfirmation->id,
                        'sale_order_id' => $warehouseConfirmation->sale_order_id
                    ]);
                }
            }
        });

        static::deleting(function ($warehouseConfirmation) {
            // Delete associated delivery order when warehouse confirmation is deleted
            if ($warehouseConfirmation->saleOrder) {
                $existingDO = $warehouseConfirmation->saleOrder->deliveryOrder()->first();
                
                if ($existingDO) {
                    $existingDO->delete();
                    
                    Log::info('Delivery Order deleted due to warehouse confirmation deletion', [
                        'delivery_order_id' => $existingDO->id,
                        'warehouse_confirmation_id' => $warehouseConfirmation->id,
                        'sale_order_id' => $warehouseConfirmation->sale_order_id
                    ]);
                }

                // Update sale order status back to request_approve when warehouse confirmation is deleted
                // This requires re-approval process since warehouse confirmation was cancelled
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

    /**
     * Create delivery order automatically when warehouse confirmation is approved
     */
    protected static function createDeliveryOrderForConfirmedWarehouseConfirmation(WarehouseConfirmation $warehouseConfirmation): void
    {
        Log::info('WarehouseConfirmation: Creating delivery order for confirmed warehouse confirmation', [
            'warehouse_confirmation_id' => $warehouseConfirmation->id,
            'sale_order_id' => $warehouseConfirmation->sale_order_id,
        ]);

        // Load relationships
        $warehouseConfirmation->loadMissing('saleOrder.customer', 'warehouseConfirmationItems.saleOrderItem.product');

        // Cek apakah sudah ada delivery order untuk sale order ini
        $existingDO = $warehouseConfirmation->saleOrder->deliveryOrder()->first();

        if ($existingDO) {
            Log::info('Delivery order already exists for sale order', ['do_id' => $existingDO->id]);
            return;
        }

        // Hanya buat delivery order untuk tipe pengiriman 'Kirim Langsung'
        if ($warehouseConfirmation->saleOrder->tipe_pengiriman !== 'Kirim Langsung') {
            Log::info('Skipping delivery order creation - not "Kirim Langsung" type', [
                'tipe_pengiriman' => $warehouseConfirmation->saleOrder->tipe_pengiriman
            ]);
            return;
        }

        // Buat delivery order
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . $warehouseConfirmation->saleOrder->so_number . '-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id' => 1, // Default driver - should be configurable
            'vehicle_id' => 1, // Default vehicle - should be configurable
            'warehouse_id' => $warehouseConfirmation->warehouseConfirmationItems->first()->warehouse_id ?? null,
            'status' => 'draft',
            'notes' => 'Auto-generated from confirmed Warehouse Confirmation ' . $warehouseConfirmation->id,
            'created_by' => $warehouseConfirmation->confirmed_by ?? $warehouseConfirmation->saleOrder->approve_by ?? \App\Models\User::first()->id ?? 1,
        ]);

        // Buat delivery order items dari warehouse confirmation items yang confirmed
        foreach ($warehouseConfirmation->warehouseConfirmationItems as $wcItem) {
            if ($wcItem->status === 'confirmed' && $wcItem->confirmed_qty > 0) {
                DeliveryOrderItem::create([
                    'delivery_order_id' => $deliveryOrder->id,
                    'sale_order_item_id' => $wcItem->sale_order_item_id,
                    'product_id' => $wcItem->saleOrderItem->product_id ?? null,
                    'quantity' => $wcItem->confirmed_qty,
                    'reason' => 'From warehouse confirmation'
                ]);
            }
        }

        // Hubungkan delivery order dengan sale order melalui pivot table
        $warehouseConfirmation->saleOrder->deliveryOrder()->attach($deliveryOrder->id);

        Log::info('Delivery order created successfully', [
            'do_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
            'warehouse_confirmation_id' => $warehouseConfirmation->id,
            'sale_order_id' => $warehouseConfirmation->sale_order_id,
        ]);
    }
}
