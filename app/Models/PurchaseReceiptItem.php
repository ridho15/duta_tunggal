<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class PurchaseReceiptItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;
    protected $table = 'purchase_receipt_items';
    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id', // Now optional
        'product_id',
        'qty_received',
        'qty_accepted',
        'qty_rejected',
        'reason_rejected',
        'warehouse_id',
        'status', // pending | completed
        'rak_id', // optional
    ];

    protected $appends = ['status_label'];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status ?? 'pending') {
            'completed' => 'Completed',
            default     => 'Pending',
        };
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark this item as completed and propagate to parent receipt.
     */
    public function markCompleted(): void
    {
        $this->update(['status' => 'completed']);
        optional($this->purchaseReceipt)->checkAndUpdateStatus();
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseReceiptItemPhoto()
    {
        return $this->hasMany(PurchaseReceiptItemPhoto::class, 'purchase_receipt_item_id');
    }

    public function purchaseReceiptItemNominal()
    {
        return $this->hasMany(PurchaseReceiptItemNominal::class, 'purchase_receipt_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function qualityControl()
    {
        return $this->hasOne(\App\Models\QualityControl::class, 'from_model_id', 'id')
                    ->where('from_model_type', \App\Models\PurchaseReceiptItem::class)
                    ->withDefault();
    }

    public function resolvedQualityControl(): ?QualityControl
    {
        $directQualityControl = $this->qualityControl;

        if ($directQualityControl?->exists) {
            return $directQualityControl;
        }

        $purchaseOrderQualityControl = $this->purchaseOrderItem?->qualityControl;

        if ($purchaseOrderQualityControl?->exists) {
            return $purchaseOrderQualityControl;
        }

        return $directQualityControl;
    }
}
