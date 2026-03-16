<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRequest extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'order_requests';
    protected $fillable = [
        'request_number',
        'warehouse_id',
        'supplier_id',
        'cabang_id',
        'request_date',
        'status', // draft, approved, rejected, closed
        'note',
        'tax_type', // PPN Included, PPN Excluded
        'created_by'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(\App\Models\Cabang::class, 'cabang_id')->withDefault();
    }

    public function orderRequestItem()
    {
        return $this->hasMany(OrderRequestItem::class, 'order_request_id');
    }

    public function purchaseOrder()
    {
        return $this->morphOne(PurchaseOrder::class, 'refer_model')->withDefault();
    }

    /**
     * All Purchase Orders created from this Order Request (supports multiple POs).
     */
    public function purchaseOrders()
    {
        return $this->morphMany(PurchaseOrder::class, 'refer_model');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    /**
     * Auto-transition status based on fulfilled_quantity across all items.
     * Call this whenever a PurchaseOrderItem or PurchaseReceiptItem is saved.
     *
     * Transitions:
     *  - All items fully fulfilled → complete
     *  - At least one item partially fulfilled → partial
     *  - Nothing fulfilled → stays at approved
     */
    public function syncFulfillmentStatus(): void
    {
        // Only auto-transition from approved/partial states; never touch draft/closed/rejected.
        if (!in_array($this->status, ['approved', 'partial', 'complete'])) {
            return;
        }

        $items = $this->orderRequestItem()->withoutTrashed()->get();
        if ($items->isEmpty()) {
            return;
        }

        $allFulfilled = $items->every(fn ($i) => ($i->fulfilled_quantity ?? 0) >= $i->quantity);
        $anyFulfilled = $items->some(fn ($i) => ($i->fulfilled_quantity ?? 0) > 0);

        if ($allFulfilled) {
            $this->update(['status' => 'complete']);
        } elseif ($anyFulfilled) {
            $this->update(['status' => 'partial']);
        }
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope());

        static::deleting(function ($orderRequest) {
            if ($orderRequest->isForceDeleting()) {
                $orderRequest->orderRequestItem()->forceDelete();
            } else {
                $orderRequest->orderRequestItem()->delete();
            }
        });

        static::restoring(function ($orderRequest) {
            $orderRequest->orderRequestItem()->withTrashed()->restore();
        });
    }
}
