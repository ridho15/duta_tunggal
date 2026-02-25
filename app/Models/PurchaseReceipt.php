<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Http\Controllers\HelperController;

class PurchaseReceipt extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'purchase_receipts';
    protected $fillable = [
        'receipt_number',
        'purchase_order_id', // Now optional
        'receipt_date',
        'received_by',
        'notes',
        'currency_id',
        'other_cost',
        'status', // draft, partial, completed
        'cabang_id'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withDefault();
    }

    public function purchaseReceiptItem()
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_receipt_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by')->withDefault();
    }

    public function purchaseReceiptPhoto()
    {
        return $this->hasMany(PurchaseReceiptPhoto::class, 'purchase_receipt_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function purchaseReceiptBiaya()
    {
        return $this->hasMany(PurchaseReceiptBiaya::class, 'purchase_receipt_id');
    }

    /**
     * Check all receipt items status and update receipt status accordingly.
     * - completed: all items are completed
     * - partial: some items completed
     * - draft: no items completed
     * Also cascades to PurchaseOrder completion and auto-invoice.
     */
    public function checkAndUpdateStatus(): void
    {
        $totalItems = $this->purchaseReceiptItem()->count();
        $completedItems = $this->purchaseReceiptItem()->where('status', 'completed')->count();

        if ($totalItems === 0) {
            $newStatus = 'draft';
        } elseif ($completedItems === $totalItems) {
            $newStatus = 'completed';
        } elseif ($completedItems > 0) {
            $newStatus = 'partial';
        } else {
            $newStatus = 'draft';
        }

        $this->update(['status' => $newStatus]);

        if ($newStatus === 'completed') {
            $this->cascadeToOrder();
        }
    }

    /**
     * Cascade PurchaseReceipt completion to PurchaseOrder.
     */
    protected function cascadeToOrder(): void
    {
        $purchaseOrder = $this->purchaseOrder;
        if (!$purchaseOrder || in_array($purchaseOrder->status, ['completed', 'closed'])) {
            return;
        }

        // Check if ALL receipts linked to this PO are now completed
        $allReceipts = $purchaseOrder->purchaseReceipt()->get();
        $allCompleted = $allReceipts->isNotEmpty() && $allReceipts->every(fn ($r) => $r->status === 'completed');

        // Also validate every PO item has sufficient accepted quantity across receipts
        $purchaseOrder->load('purchaseOrderItem');
        $quantitiesFulfilled = $purchaseOrder->purchaseOrderItem->every(function ($poItem) {
            $totalAccepted = $poItem->purchaseReceiptItem()->sum('qty_accepted');
            return $totalAccepted >= $poItem->quantity;
        });

        if ($allCompleted && $quantitiesFulfilled) {
            $purchaseOrder->update([
                'status'       => 'completed',
                'completed_by' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                'completed_at' => now(),
            ]);

            \Illuminate\Support\Facades\Log::info('PurchaseReceipt cascade: PO completed', ['po_id' => $purchaseOrder->id]);

            // Auto-create invoice if configured
            if (config('procurement.auto_create_invoice', false)) {
                app(\App\Services\PurchaseReceiptService::class)->createAutomaticInvoiceFromReceipt($this);
            }
        }
    }

    /**
     * Update purchase receipt status based on QC items sent status (backward compat).
     * @deprecated Use checkAndUpdateStatus() instead.
     */
    public function updateStatusBasedOnQCItems()
    {
        $totalItems = $this->purchaseReceiptItem()->count();
        $sentItems = $this->purchaseReceiptItem()->where('status', 'completed')->count();

        if ($totalItems === 0) {
            $this->status = 'draft';
        } elseif ($sentItems === $totalItems) {
            $this->status = 'completed';
        } elseif ($sentItems > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'draft';
        }

        $this->save();
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        // Removed automatic stock movement processing for pre-QC items
        // Stock movement should be done manually after QC approval

        static::deleting(function ($purchaseReceipt) {
            if ($purchaseReceipt->isForceDeleting()) {
                $purchaseReceipt->purchaseReceiptItem()->forceDelete();
                $purchaseReceipt->purchaseReceiptPhoto()->forceDelete();
                $purchaseReceipt->purchaseReceiptBiaya()->forceDelete();
            } else {
                $purchaseReceipt->purchaseReceiptItem()->delete();
                $purchaseReceipt->purchaseReceiptPhoto()->delete();
                $purchaseReceipt->purchaseReceiptBiaya()->delete();
            }
        });

        static::restoring(function ($purchaseReceipt) {
            $purchaseReceipt->purchaseReceiptItem()->withTrashed()->restore();
            $purchaseReceipt->purchaseReceiptPhoto()->withTrashed()->restore();
            $purchaseReceipt->purchaseReceiptBiaya()->withTrashed()->restore();
        });
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
