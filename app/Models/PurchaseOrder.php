<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'purchase_orders';
    protected $fillable = [
        'supplier_id',
        'po_number',
        'order_date',
        'status', //'draft','approved','partially_received','completed','closed', 'request_close', 'request_approval'
        'expected_date',
        'total_amount',
        'is_asset',
        'close_reason',
        'date_approved',
        'approved_by',
        'approval_signature',
        'approval_signed_at',
        'warehouse_id',
        'tempo_hutang', // hari
        'note',
        'close_requested_by',
        'close_requested_at',
        'closed_by',
        'closed_at',
        'close_reason',
        'completed_by',
        'completed_at',
        'created_by',
        'refer_model_type',
        'refer_model_id',
        'is_import',
        'ppn_option',
        'cabang_id'
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'date_approved' => 'date',
            'approval_signed_at' => 'datetime',
        ];
    }

    public function purchaseOrderCurrency()
    {
        return $this->hasMany(PurchaseOrderCurrency::class, 'purchase_order_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function purchaseReceipt()
    {
        return $this->hasMany(PurchaseReceipt::class, 'purchase_order_id');
    }

    public function closeRequestedBy()
    {
        return $this->belongsTo(User::class, 'close_requested_by')->withDefault();
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by')->withDefault();
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function referModel()
    {
        return $this->morphTo(__FUNCTION__, 'refer_model_type', 'refer_model_id')->withDefault();
    }

    public function invoice()
    {
        return $this->morphMany(Invoice::class, 'from_model');
    }

    public function purchaseOrderBiaya()
    {
        return $this->hasMany(PurchaseOrderBiaya::class, 'purchase_order_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'purchase_order_id');
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope());

        static::deleting(function ($purchaseOrder) {
            if ($purchaseOrder->isForceDeleting()) {
                $purchaseOrder->purchaseOrderItem()->forceDelete();
                $purchaseOrder->purchaseReceipt()->forceDelete();
                $purchaseOrder->invoice()->forceDelete();
                $purchaseOrder->purchaseOrderBiaya()->forceDelete();
                $purchaseOrder->assets()->forceDelete();
            } else {
                $purchaseOrder->purchaseOrderItem()->delete();
                $purchaseOrder->purchaseReceipt()->delete();
                $purchaseOrder->invoice()->delete();
                $purchaseOrder->purchaseOrderBiaya()->delete();
                $purchaseOrder->assets()->delete();
            }
        });

        static::restoring(function ($purchaseOrder) {
            $purchaseOrder->purchaseOrderItem()->withTrashed()->restore();
            $purchaseOrder->purchaseReceipt()->withTrashed()->restore();
            $purchaseOrder->invoice()->withTrashed()->restore();
            $purchaseOrder->purchaseOrderBiaya()->withTrashed()->restore();
            $purchaseOrder->assets()->withTrashed()->restore();
        });
    }

    public function getRemainingQtyStatusAttribute()
    {
        $totalItems = $this->purchaseOrderItem->count();
        $completedItems = $this->purchaseOrderItem->filter(function ($item) {
            return $item->remaining_quantity <= 0;
        })->count();

        $itemsWithReceipts = $this->purchaseOrderItem->filter(function ($item) {
            return $item->purchaseReceiptItem()->sum('qty_accepted') > 0;
        })->count();

        if ($totalItems === 0) return 'No Items';
        if ($completedItems === $totalItems) return 'Semua Diterima';
        if ($completedItems > 0) return 'Sebagian (' . $completedItems . '/' . $totalItems . ')';
        if ($itemsWithReceipts > 0) return 'Sebagian Diterima';
        return 'Belum Diterima';
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
