<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReceiptItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'customer_receipt_items';
    protected $fillable = [
        'customer_receipt_id',
        'method',
        'amount',
        'coa_id',
        'payment_date',
    ];

    public function customerReceipt()
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }
}
