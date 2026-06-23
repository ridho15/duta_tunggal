<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;
    protected $table = 'purchase_orders';
    protected $fillable = [
        'supplier_id',
        'cabang_id',
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
        'top_type',
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
        'is_import'
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

    public function getTotalAmountAttribute($value)
    {
        if ($value === null) return null;
        return number_format((float) $value, 2, '.', '');
    }

    public function getCabangIdAttribute($value)
    {
        if (!empty($value)) {
            return (int) $value;
        }

        // Fall back to the items' referenced model or product cabang
        foreach ($this->purchaseOrderItem as $item) {
            $referModel = $item->referItemModel;
            if ($referModel && !empty($referModel->cabang_id)) {
                return (int) $referModel->cabang_id;
            }
            if ($item->product && !empty($item->product->cabang_id)) {
                return (int) $item->product->cabang_id;
            }
        }

        return null;
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

        // Defensive: strip attributes that do not exist in DB schema to avoid
        // SQL errors when older fixtures/tests still pass legacy columns.
        static::saving(function ($purchaseOrder) {
            try {
                $table = $purchaseOrder->getTable();
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                foreach (array_keys($purchaseOrder->getAttributes()) as $attr) {
                    if (! in_array($attr, $columns, true)) {
                        unset($purchaseOrder[$attr]);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function getRemainingQtyStatusAttribute()
    {
        return $this->receiptFulfillmentSummary()['status_label'];
    }

    public function receiptFulfillmentSummary(): array
    {
        $this->loadMissing('purchaseOrderItem.purchaseReceiptItem');

        $totalItems = $this->purchaseOrderItem->count();
        $completedItems = 0;
        $itemsWithReceiptActivity = 0;
        $totalOrdered = 0.0;
        $totalReceived = 0.0;
        $totalAccepted = 0.0;

        foreach ($this->purchaseOrderItem as $item) {
            $ordered = (float) ($item->quantity ?? 0);
            $received = (float) $item->purchaseReceiptItem->sum('qty_received');
            $accepted = (float) $item->purchaseReceiptItem->sum('qty_accepted');

            $totalOrdered += $ordered;
            $totalReceived += $received;
            $totalAccepted += $accepted;

            if ($received > 0 || $accepted > 0) {
                $itemsWithReceiptActivity++;
            }

            if ($ordered <= 0 || $accepted >= $ordered) {
                $completedItems++;
            }
        }

        $allAccepted = $totalItems > 0 && $completedItems === $totalItems;

        $statusLabel = match (true) {
            $totalItems === 0 => 'No Items',
            $allAccepted => 'Semua Diterima',
            $itemsWithReceiptActivity > 0 || $totalReceived > 0 || $totalAccepted > 0 => 'Sebagian Diterima',
            default => 'Belum Diterima',
        };

        return [
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'items_with_receipt_activity' => $itemsWithReceiptActivity,
            'total_ordered' => $totalOrdered,
            'total_received' => $totalReceived,
            'total_accepted' => $totalAccepted,
            'all_received' => $allAccepted,
            'status_label' => $statusLabel,
        ];
    }

    public function syncReceiptFulfillmentStatus(?int $userId = null): void
    {
        if (in_array($this->status, ['closed', 'paid'], true)) {
            return;
        }

        $summary = $this->receiptFulfillmentSummary();
        $newStatus = match ($summary['status_label']) {
            'Semua Diterima' => 'completed',
            'Sebagian Diterima' => 'partially_received',
            default => $this->status === 'completed' ? 'approved' : $this->status,
        };

        if ($newStatus === $this->status) {
            return;
        }

        $this->update([
            'status' => $newStatus,
            'completed_by' => $newStatus === 'completed' ? ($userId ?? \Illuminate\Support\Facades\Auth::id() ?? 1) : null,
            'completed_at' => $newStatus === 'completed' ? now() : null,
        ]);
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
        // Skip if assets already created by observer auto-complete path
        if ($this->is_asset && !Asset::where('purchase_order_id', $this->id)->exists()) {
            $defaultAssetCoa = \App\Models\ChartOfAccount::where('code', config('asset.coa.asset', '1210.01'))->first();
            $defaultAccumCoa = \App\Models\ChartOfAccount::where('code', config('asset.coa.accumulated_depreciation', '1220.01'))->first();
            $defaultExpenseCoa = \App\Models\ChartOfAccount::where('code', config('asset.coa.depreciation_expense', '6311'))->first();

            if (! $defaultAccumCoa) {
                $defaultAccumCoa = \App\Models\ChartOfAccount::where('type', 'Contra Asset')->first();
            }

            if (! $defaultExpenseCoa) {
                $defaultExpenseCoa = \App\Models\ChartOfAccount::where('type', 'Expense')
                    ->where('name', 'like', '%penyusutan%')
                    ->first()
                    ?? \App\Models\ChartOfAccount::where('type', 'Expense')->first();
            }

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
                    'asset_coa_id' => $item->product->inventory_coa_id ?? $defaultAssetCoa?->id,
                    'accumulated_depreciation_coa_id' => $defaultAccumCoa?->id ?? $defaultAssetCoa?->id,
                    'depreciation_expense_coa_id' => $defaultExpenseCoa?->id ?? $defaultAssetCoa?->id,
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

        // Asset PO can be completed directly after approval/partial receive
        if ($this->is_asset) {
            return in_array($this->status, ['approved', 'partially_received']);
        }

        // Must have at least one receipt item to be completable
        $hasReceiptItems = $this->purchaseOrderItem()
            ->whereHas('purchaseReceiptItem')
            ->exists();

        return $hasReceiptItems;
    }
}
