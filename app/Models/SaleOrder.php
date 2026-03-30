<?php

namespace App\Models;

use App\Models\InventoryStock;
use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SaleOrder extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'sale_orders';
    protected $casts = [
        'order_date' => 'datetime',
        'delivery_date' => 'datetime',
        'request_approve_at' => 'datetime',
        'request_close_at' => 'datetime',
        'approve_at' => 'datetime',
        'close_at' => 'datetime',
        'completed_at' => 'datetime',
        'reject_at' => 'datetime',
        'warehouse_confirmed_at' => 'datetime',
    ];
    protected $fillable = [
        'customer_id',
        'quotation_id',
        'so_number',
        'order_date',
        'status', // draft, request_approve, request_close, approved, closed, completed, confirmed, received, canceled, 'reject
        'delivery_date',
        'total_amount',
        'request_approve_by',
        'request_approve_at',
        'request_close_by',
        'request_close_at',
        'approve_by',
        'approve_at',
        'close_by',
        'close_at',
        'completed_at',
        'shipped_to',
        'reject_by',
        'reject_at',
        'reason_close',
        'tipe_pengiriman', // Ambil Sendiri, Kirim Langsung
        'tempo_pembayaran',
        'created_by',
        'warehouse_confirmed_at',
        'cabang_id'
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    /**
     * Alias for saleOrderItem() to support legacy/consumers expecting ->items
     */
    public function items()
    {
        return $this->saleOrderItem();
    }

    public function requestApproveBy()
    {
        return $this->belongsTo(User::class, 'request_approve_by')->withDefault();
    }

    public function requestCloseBy()
    {
        return $this->belongsTo(User::class, 'request_close_by')->withDefault();
    }

    public function approveBy()
    {
        return $this->belongsTo(User::class, 'approve_by')->withDefault();
    }

    public function closeBy()
    {
        return $this->belongsTo(User::class, 'close_by')->withDefault();
    }

    public function rejectBy()
    {
        return $this->belongsTo(User::class, 'reject_by')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function deliveryOrder()
    {
        return $this->belongsToMany(DeliveryOrder::class, 'delivery_sales_orders', 'sales_order_id', 'delivery_order_id');
    }

    public function deliverySalesOrder()
    {
        return $this->hasMany(DeliverySalesOrder::class, 'sales_order_id');
    }

    /**
     * Warehouse confirmations linked to this SO via polymorphic relationship.
     * Previously hasOne('sale_order_id') — now morphMany so multiple WCs per SO are supported.
     */
    public function warehouseConfirmations()
    {
        return $this->morphMany(WarehouseConfirmation::class, 'confirmable');
    }

    /** Alias for backward-compatible single-record access. */
    public function warehouseConfirmation()
    {
        return $this->morphOne(WarehouseConfirmation::class, 'confirmable')->latestOfMany();
    }

    public function purchaseOrder()
    {
        return $this->morphMany(PurchaseOrder::class, 'refer_model');
    }

    public function depositLog()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }

    /**
     * Inverse relation to invoices created from this Sale Order.
     * Filament resources expect a `salesInvoices` relation for eager-loading.
     */
    public function salesInvoices()
    {
        // Use the same polymorphic column names as Invoice::fromModel()
        // Invoice::fromModel() uses ('from_model_type', 'from_model_id') explicitly,
        // so provide those here to avoid Laravel looking for `fromModel_id`.
        return $this->morphMany(\App\Models\Invoice::class, 'fromModel', 'from_model_type', 'from_model_id');
    }

    /**
     * Check if any items in this sale order have insufficient stock
     */
    public function hasInsufficientStock()
    {
        foreach ($this->saleOrderItem as $item) {
            $allocations = $item->warehouseAllocations;

            if ($allocations->isNotEmpty()) {
                $allocatedTotal = (float) $allocations->sum('quantity');
                if (abs($allocatedTotal - (float) $item->quantity) > 0.0001) {
                    return true;
                }

                foreach ($allocations as $allocation) {
                    $availableStock = InventoryStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $allocation->warehouse_id)
                        ->sum('qty_available');

                    if ((float) $availableStock < (float) $allocation->quantity) {
                        return true;
                    }
                }
            } else {
                $availableStock = InventoryStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->where('rak_id', $item->rak_id)
                    ->sum('qty_available');

                if ($availableStock < $item->quantity) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get items with insufficient stock
     */
    public function getInsufficientStockItems()
    {
        $insufficientItems = [];
        foreach ($this->saleOrderItem as $item) {
            $allocations = $item->warehouseAllocations;

            if ($allocations->isNotEmpty()) {
                $allocatedTotal = (float) $allocations->sum('quantity');
                if (abs($allocatedTotal - (float) $item->quantity) > 0.0001) {
                    $insufficientItems[] = [
                        'item' => $item,
                        'available' => $allocatedTotal,
                        'needed' => $item->quantity,
                        'shortage' => $item->quantity - $allocatedTotal
                    ];
                    continue;
                }

                foreach ($allocations as $allocation) {
                    $availableStock = InventoryStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $allocation->warehouse_id)
                        ->sum('qty_available');

                    if ((float) $availableStock < (float) $allocation->quantity) {
                        $insufficientItems[] = [
                            'item' => $item,
                            'available' => $availableStock,
                            'needed' => $allocation->quantity,
                            'shortage' => (float) $allocation->quantity - (float) $availableStock
                        ];
                    }
                }
            } else {
                $availableStock = InventoryStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->where('rak_id', $item->rak_id)
                    ->sum('qty_available');
                if ($availableStock < $item->quantity) {
                    $insufficientItems[] = [
                        'item' => $item,
                        'available' => $availableStock,
                        'needed' => $item->quantity,
                        'shortage' => $item->quantity - $availableStock
                    ];
                }
            }
        }
        return $insufficientItems;
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope());

        static::deleting(function ($saleOrder) {
            if ($saleOrder->isForceDeleting()) {
                $saleOrder->saleOrderItem()->forceDelete();
                $saleOrder->deliverySalesOrder()->forceDelete();
                // Use query builder (with parens) — safe even when no record exists
                $saleOrder->warehouseConfirmations()->forceDelete();
                $saleOrder->purchaseOrder()->forceDelete();
                $saleOrder->depositLog()->forceDelete();
            } else {
                $saleOrder->saleOrderItem()->delete();
                $saleOrder->deliverySalesOrder()->delete();
                $saleOrder->warehouseConfirmations()->delete();
                $saleOrder->purchaseOrder()->delete();
                $saleOrder->depositLog()->delete();
            }
        });

        static::restoring(function ($saleOrder) {
            $saleOrder->saleOrderItem()->withTrashed()->restore();
            $saleOrder->deliverySalesOrder()->withTrashed()->restore();
            $saleOrder->warehouseConfirmations()->withTrashed()->restore();
            $saleOrder->purchaseOrder()->withTrashed()->restore();
            $saleOrder->depositLog()->withTrashed()->restore();
        });
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
