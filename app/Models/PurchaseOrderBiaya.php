<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderBiaya extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'purchase_order_biayas';
    protected $fillable = [
        'purchase_order_id',
        'currency_id',
        'coa_id',
        'nama_biaya',
        'total',
        'untuk_pembelian', // Non Pajak, Pajak
        'masuk_invoice'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function purchaseReceiptBiayas()
    {
        return $this->hasMany(PurchaseReceiptBiaya::class, 'purchase_order_biaya_id');
    }
}
