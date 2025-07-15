<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivable extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity;
    protected $table = 'account_receivables';
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'total',
        'paid',
        'remaining',
        'status' //Lunas / Belum Lunas
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function ageingSchedule()
    {
        return $this->morphOne(AgeingSchedule::class, 'from_model')->withDefault();
    }
}
