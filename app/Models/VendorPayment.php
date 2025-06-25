<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorPayment extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'vendor_payments';
    protected $fillable = [
        'invoice_id',
        'supplier_id',
        'payment_date',
        'ntpn',
        'total_payment',
        'notes',
        'diskon',
        'status', //'Draft', 'Partial', 'Paid'

    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function vendorPaymentDetail()
    {
        return $this->hasMany(VendorPaymentDetail::class, 'vendor_payment_id');
    }
}
