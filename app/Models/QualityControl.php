<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityControl extends Model
{
    use SoftDeletes, LogsGlobalActivity, HasFactory;
    protected $table = 'quality_controls';
    protected $fillable = [
        'qc_number',
        'inspected_by',
        'passed_quantity',
        'rejected_quantity',
        'notes',
        'status',  // send to stock / send return product
        'warehouse_id',
        'reason_reject',
        'product_id',
        'date_send_stock',
        'rak_id',
        'from_model_id',
        'from_model_type',
        'purchase_return_processed',
    ];

    protected $appends = [
        'status_formatted'
    ];

    public function getStatusFormattedAttribute()
    {
        if ($this->status == 1 || $this->status == true) {
            return 'Sudah diproses';
        } else {
            return 'Belum diproses';
        }
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'from_model')->withDefault();
    }

    public function returnProduct()
    {
        return $this->morphOne(ReturnProduct::class, 'from_model')->withDefault();
    }

    public function returnProductItem()
    {
        return $this->morphMany(ReturnProductItem::class, 'from_item_model');
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function purchaseReceipt()
    {
        // Jika fromModel adalah PurchaseReceiptItem, kembalikan purchaseReceipt-nya
        if ($this->fromModel instanceof PurchaseReceiptItem) {
            return $this->fromModel->purchaseReceipt();
        }
        
        // Jika tidak, kembalikan null atau default
        return null;
    }
}
