<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'delivery_orders';
    protected $fillable = [
        'do_number',
        'delivery_date',
        'driver_id',
        'vehicle_id',
        'warehouse_id',
        'status', // 'draft', 'sent', 'received', 'supplier', 'completed', 'request_approve', 'approved', 'request_close', 'closed', 'reject'
        'notes',
        'additional_cost',
        'additional_cost_description',
        'created_by',
        'cabang_id'
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id')->withDefault();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id')->withDefault();
    }

    public function deliveryOrderItem()
    {
        return $this->hasMany(DeliveryOrderItem::class, 'delivery_order_id');
    }

    public function salesOrders()
    {
        return $this->belongsToMany(SaleOrder::class, 'delivery_sales_orders', 'delivery_order_id', 'sales_order_id');
    }

    public function suratJalan()
    {
        return $this->belongsToMany(SuratJalan::class, 'surat_jalan_delivery_orders', 'delivery_order_id', 'surat_jalan_id')->withTimestamps();
    }

    public function deliverySchedules()
    {
        return $this->belongsToMany(DeliverySchedule::class, 'delivery_schedule_delivery_orders', 'delivery_order_id', 'delivery_schedule_id')->withTimestamps();
    }

    public function deliverySalesOrder()
    {
        return $this->hasMany(DeliverySalesOrder::class, 'delivery_order_id');
    }

    public function log()
    {
        return $this->hasMany(DeliveryOrderLog::class, 'delivery_order_id');
    }

    public function returnProduct(){
        return $this->morphOne(ReturnProduct::class, 'from_model')->withDefault();
    }

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'from_model')->withDefault();
    }

    public function approvalLogs()
    {
        return $this->hasMany(DeliveryOrderApprovalLog::class, 'delivery_order_id');
    }

    /**
     * Calculate total value of delivery order based on sale order items pricing
     */
    public function getTotalAttribute()
    {
        $total = 0;
        
        foreach ($this->deliveryOrderItem as $item) {
            if ($item->saleOrderItem) {
                $price = $item->saleOrderItem->unit_price - $item->saleOrderItem->discount + $item->saleOrderItem->tax;
                $total += $price * $item->quantity;
            }
        }
        
        return $total;
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        static::deleting(function ($deliveryOrder) {
            if ($deliveryOrder->isForceDeleting()) {
                $deliveryOrder->deliveryOrderItem()->forceDelete();
            } else {
                $deliveryOrder->deliveryOrderItem()->delete();
            }
        });

        static::restoring(function ($deliveryOrder) {
            $deliveryOrder->deliveryOrderItem()->withTrashed()->restore();
        });
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class, 'delivery_order_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    // WC records linked to this DO via polymorphic relationship (DO-centric flow)
    public function warehouseConfirmations()
    {
        return $this->morphMany(WarehouseConfirmation::class, 'confirmable');
    }

    /**
     * Update DO status based on all linked WC outcomes.
     * - ALL confirmed  → approved (auto)
     * - ANY rejected   → reject (auto)
     * - still pending  → stays request_stock
     */
    public function updateStatusFromWarehouseConfirmations(): void
    {
        $wcs = $this->warehouseConfirmations()->get();
        if ($wcs->isEmpty()) {
            return;
        }

        $statuses = $wcs->map(fn ($wc) => strtolower((string) $wc->status))->values();

        $allConfirmed = $statuses->every(fn ($status) => $status === 'confirmed');
        $anyRejected  = $statuses->contains('rejected');

        if ($allConfirmed) {
            $this->update(['status' => 'approved']);
        } elseif ($anyRejected) {
            $this->update(['status' => 'reject']);
        }
        // else: one or more WCs still pending → stay at request_stock
    }
}
