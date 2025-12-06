<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class AccountPayable extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'account_payables';
    protected $casts = [
        'total' => 'float',
        'paid' => 'float',
        'remaining' => 'float',
    ];

    protected $fillable = [
        'invoice_id',
        'supplier_id',
        'total',
        'paid',
        'remaining',
        'status', //Lunas / Belum Lunas
        'created_by'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withTrashed()->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function ageingSchedule()
    {
        return $this->morphOne(AgeingSchedule::class, 'from_model')->withDefault();
    }

    public function vendorPaymentDetails()
    {
        return $this->hasMany(VendorPaymentDetail::class, 'invoice_id', 'invoice_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($accountPayable) {
            // Hapus ageing schedule ketika account payable dihapus
            if ($accountPayable->ageingSchedule) {
                \Illuminate\Support\Facades\Log::info('Deleting ageing schedule for AccountPayable ID: ' . $accountPayable->id);
                $accountPayable->ageingSchedule->delete();
            }
        });

        static::updated(function ($accountPayable) {
            // Hapus ageing schedule ketika account payable lunas
            if ($accountPayable->status === 'Lunas' && $accountPayable->wasChanged('status')) {
                if ($accountPayable->ageingSchedule) {
                    $accountPayable->ageingSchedule->delete();
                }
            }
        });
    }
}
