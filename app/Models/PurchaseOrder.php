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
        'status', //'draft','approved','partially_received','completed','closed', 'request_close'
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

    /**
     * Manually complete Purchase Order
     * This can be triggered via button/action
     */
    public function manualComplete($userId = null)
    {
        if (in_array($this->status, ['completed', 'closed', 'paid'])) {
            throw new \Exception('Purchase Order is already ' . $this->status);
        }

        $this->update([
            'status' => 'completed',
            'completed_by' => $userId ?? \Illuminate\Support\Facades\Auth::id() ?? 1,
            'completed_at' => now(),
        ]);

        // If this purchase order represents an asset purchase, create asset records
        if ($this->is_asset) {
            foreach ($this->purchaseOrderItem as $item) {
                $total = \App\Http\Controllers\HelperController::hitungSubtotal(
                    (int)$item->quantity,
                    (int)$item->unit_price,
                    (int)$item->discount,
                    (int)$item->tax,
                    $item->tipe_pajak
                );

                $asset = Asset::create([
                    'name' => $item->product->name,
                    'product_id' => $item->product_id,
                    'purchase_order_id' => $this->id,
                    'purchase_order_item_id' => $item->id,
                    'purchase_date' => $this->order_date,
                    'usage_date' => $this->order_date,
                    'purchase_cost' => $total,
                    'salvage_value' => 0,
                    'useful_life_years' => 5,
                    'asset_coa_id' => $item->product->inventory_coa_id ?? null,
                    'accumulated_depreciation_coa_id' => null,
                    'depreciation_expense_coa_id' => null,
                    'status' => 'active',
                    'notes' => 'Generated from PO ' . $this->po_number,
                ]);

                try {
                    $asset->calculateDepreciation();
                } catch (\Throwable $e) {
                    // If depreciation calculation fails, log and continue
                    \Illuminate\Support\Facades\Log::warning('Failed to calculate depreciation for asset', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }
            }
        }

        \Illuminate\Support\Facades\Log::info('Manually completed Purchase Order', [
            'po_id' => $this->id,
            'po_number' => $this->po_number,
            'completed_by' => $this->completed_by,
        ]);

        return $this;
    }

    /**
     * Check if PO can be manually completed
     */
    public function canBeCompleted(): bool
    {
        // Can't complete if already completed, closed, or paid
        if (in_array($this->status, ['completed', 'closed', 'paid'])) {
            return false;
        }

        // Must have at least one receipt item to be completable
        $hasReceiptItems = $this->purchaseOrderItem()
            ->whereHas('purchaseReceiptItem')
            ->exists();

        return $hasReceiptItems;
    }
}