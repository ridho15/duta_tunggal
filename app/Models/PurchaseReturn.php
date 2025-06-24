<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'purchase_returns';
    protected $fillable = [
        'purchase_receipt_id',
        'return_date',
        'nota_retur',
        'created_by',
        'notes'
    ];

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function purchaseReturnItem()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }
}
