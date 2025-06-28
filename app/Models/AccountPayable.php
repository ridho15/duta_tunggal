<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountPayable extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'account_payables';
    protected $fillable = [
        'invoice_id',
        'supplier_id',
        'total',
        'paid',
        'remaining',
        'status' //Lunas / Belum Lunas
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function ageingSchedule()
    {
        return $this->hasOne(AgeingSchedule::class, 'account_payable_id')->withDefault();
    }
}
