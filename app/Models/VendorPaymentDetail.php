<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorPaymentDetail extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'vendor_payment_details';
    protected $fillable = [
        'vendor_payment_id',
        'method',
        'amount',
        'coa_id',
        'payment_date'
    ];

    public function vendorPayment()
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function depositLog()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }
}
