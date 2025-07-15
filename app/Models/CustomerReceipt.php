<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReceipt extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'customer_receipts';
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'payment_date',
        'ntpn',
        'total_payment',
        'notes',
        'diskon',
        'status' // 'Draft','Partial','Paid'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function customerReceiptItem()
    {
        return $this->hasMany(CustomerReceiptItem::class, 'customer_receipt_id');
    }
}
