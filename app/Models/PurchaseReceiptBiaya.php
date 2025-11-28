<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptBiaya extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'purchase_receipt_biayas';
    protected $fillable = [
        'purchase_receipt_id',
        'currency_id',
        'coa_id',
        'nama_biaya',
        'total',
        'untuk_pembelian', // Non Pajak, Pajak
        'masuk_invoice',
        'purchase_order_biaya_id',
    ];

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function purchaseOrderBiaya()
    {
        return $this->belongsTo(PurchaseOrderBiaya::class, 'purchase_order_biaya_id');
    }
}