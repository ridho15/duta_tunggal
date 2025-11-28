<?php

namespace App\Models;

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
        'purchase_order_id',
        'receipt_date',
        'received_by',
        'notes',
        'currency_id',
        'other_cost',
        'status' // draft, partial, completed
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
     * Update purchase receipt status based on QC items sent status
     * - completed: all items sent to QC
     * - partial: some items sent to QC
     * - draft: no items sent to QC
     */
    public function updateStatusBasedOnQCItems()
    {
        $totalItems = $this->purchaseReceiptItem()->count();
        $sentItems = $this->purchaseReceiptItem()->where('is_sent', true)->count();

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
}
