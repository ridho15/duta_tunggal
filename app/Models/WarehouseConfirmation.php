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
        'delivery_order_id',  // H4: link to DO when auto-created from request_stock
        'confirmation_type',
        'note',
        'rejection_reason',   // I1: reason when WC is rejected
        'status', // Confirmed / Rejected / Request / confirmed / rejected / partial_confirmed
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

    // H4: WC created from DO's request_stock action
    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
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
                    'status' => 'confirmed',
                    'warehouse_confirmed_at' => now()
                ]);

                Log::info('Sale Order status and warehouse_confirmed_at updated after warehouse confirmation auto-approval', [
                    'sale_order_id' => $warehouseConfirmation->sale_order_id,
                    'warehouse_confirmation_id' => $warehouseConfirmation->id
                ]);

                // Note: Delivery order creation is now handled in SaleOrderObserver after WC items are created
            }
        });

        static::updating(function ($warehouseConfirmation) {
            $originalStatus = $warehouseConfirmation->getOriginal('status');
            $newStatus = $warehouseConfirmation->status;

            // H4/I1: when this WC is DO-linked and status changes → update DO status
            if ($warehouseConfirmation->isDirty('status') && $warehouseConfirmation->delivery_order_id) {
                $warehouseConfirmation->_triggerDoStatusUpdate = true;
            }

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
                        'status' => 'confirmed',
                        'warehouse_confirmed_at' => now()
                    ]);

                    Log::info('Sale Order status and warehouse_confirmed_at updated after warehouse confirmation approval', [
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
            $warehouseConfirmation->warehouseConfirmationItems()->withTrashed()->restore();
        });

        // H4/I1: after status saved, update linked DO status
        static::updated(function ($warehouseConfirmation) {
            if (! empty($warehouseConfirmation->_triggerDoStatusUpdate)
                && $warehouseConfirmation->delivery_order_id) {
                $do = DeliveryOrder::find($warehouseConfirmation->delivery_order_id);
                $do?->updateStatusFromWarehouseConfirmations();
            }
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
            // If the existing DO has no items, try to populate it (can happen if observer fired before WC items were created)
            if ($existingDO->deliveryOrderItem()->count() === 0 && $warehouseConfirmation->warehouseConfirmationItems->isNotEmpty()) {
                Log::info('Existing DO has no items — adding items now', ['do_id' => $existingDO->id]);
                foreach ($warehouseConfirmation->warehouseConfirmationItems as $wcItem) {
                    if ($wcItem->status === 'confirmed' && $wcItem->confirmed_qty > 0) {
                        try {
                            DeliveryOrderItem::create([
                                'delivery_order_id' => $existingDO->id,
                                'sale_order_item_id' => $wcItem->sale_order_item_id,
                                'product_id'         => $wcItem->saleOrderItem->product_id ?? null,
                                'quantity'           => $wcItem->confirmed_qty,
                                'reason'             => 'From warehouse confirmation',
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('Failed to add item to existing DO', [
                                'do_id' => $existingDO->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } else {
                Log::info('Delivery order already exists for sale order', ['do_id' => $existingDO->id]);
                \Filament\Notifications\Notification::make()
                    ->title('Gagal Membuat Delivery Order')
                    ->danger()
                    ->body('Delivery Order sudah ada untuk Sale Order ini. Tidak boleh membuat lebih dari satu Delivery Order untuk satu Sale Order.')
                    ->send();
            }
            return;
        }

        // Hanya buat delivery order untuk tipe pengiriman yang relevan
        // Both 'Kirim Langsung' and 'Ambil Sendiri' now get DOs for tracking purposes
        if (!in_array($warehouseConfirmation->saleOrder->tipe_pengiriman, ['Kirim Langsung', 'Ambil Sendiri'])) {
            Log::info('Skipping delivery order creation - unrecognized tipe_pengiriman', [
                'tipe_pengiriman' => $warehouseConfirmation->saleOrder->tipe_pengiriman
            ]);
            return;
        }

        // Resolve warehouse from WC items (nullable in schema, so null is safe)
        $warehouseId = $warehouseConfirmation->warehouseConfirmationItems->first()?->warehouse_id;

        // Resolve driver and vehicle — these are optional at auto-creation time.
        // driver_id and vehicle_id are nullable (migration 2026_03_11_030000) so that
        // the DO can be created without master data and filled in by the user later.
        $driver  = \App\Models\Driver::first();
        $vehicle = \App\Models\Vehicle::first();

        // Generate a collision-safe DO number
        $doNumber = \App\Services\DeliveryOrderService::generateStaticDoNumber();

        try {
            // Buat delivery order
            $deliveryOrder = DeliveryOrder::create([
                'do_number'    => $doNumber,
                'delivery_date' => now()->toDateString(),
                'driver_id'    => $driver?->id,
                'vehicle_id'   => $vehicle?->id,
                'warehouse_id' => $warehouseId,
                'status'       => 'draft',
                'notes'        => 'Auto-generated from confirmed Warehouse Confirmation ' . $warehouseConfirmation->id,
                'created_by'   => $warehouseConfirmation->confirmed_by
                    ?? $warehouseConfirmation->saleOrder->approve_by
                    ?? \App\Models\User::first()?->id,
                // Pastikan cabang_id diisi agar DO muncul di filter CabangScope
                'cabang_id'    => $warehouseConfirmation->saleOrder->cabang_id
                    ?? \Illuminate\Support\Facades\Auth::user()?->cabang_id
                    ?? \App\Models\Cabang::first()?->id,
            ]);

            // Buat delivery order items dari warehouse confirmation items yang confirmed
            foreach ($warehouseConfirmation->warehouseConfirmationItems as $wcItem) {
                if ($wcItem->status === 'confirmed' && $wcItem->confirmed_qty > 0) {
                    DeliveryOrderItem::create([
                        'delivery_order_id' => $deliveryOrder->id,
                        'sale_order_item_id' => $wcItem->sale_order_item_id,
                        'product_id'         => $wcItem->saleOrderItem->product_id ?? null,
                        'quantity'           => $wcItem->confirmed_qty,
                        'reason'             => 'From warehouse confirmation',
                    ]);
                }
            }

            // Hubungkan delivery order dengan sale order melalui pivot table
            $warehouseConfirmation->saleOrder->deliveryOrder()->attach($deliveryOrder->id);

            Log::info('Delivery order created successfully', [
                'do_id'                    => $deliveryOrder->id,
                'do_number'                => $deliveryOrder->do_number,
                'warehouse_confirmation_id' => $warehouseConfirmation->id,
                'sale_order_id'            => $warehouseConfirmation->sale_order_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WarehouseConfirmation: Failed to auto-create Delivery Order', [
                'warehouse_confirmation_id' => $warehouseConfirmation->id,
                'sale_order_id'             => $warehouseConfirmation->sale_order_id,
                'error'                     => $e->getMessage(),
                'trace'                     => $e->getTraceAsString(),
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Gagal Membuat Delivery Order')
                ->danger()
                ->body('Terjadi kesalahan otomatis saat membuat Delivery Order. Silakan cek log untuk detail.')
                ->send();
        }
    }
}
