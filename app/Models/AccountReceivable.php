<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

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
        'status', //Lunas / Belum Lunas
        'created_by',
        'cabang_id'
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function ageingSchedule()
    {
        return $this->morphOne(AgeingSchedule::class, 'from_model')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($accountReceivable) {
            // Hapus ageing schedule ketika account receivable dihapus
            if ($accountReceivable->ageingSchedule) {
                $accountReceivable->ageingSchedule->delete();
            }
        });

        static::updated(function ($accountReceivable) {
            // Hapus ageing schedule ketika account receivable lunas
            if ($accountReceivable->status === 'Lunas' && $accountReceivable->wasChanged('status')) {
                if ($accountReceivable->ageingSchedule) {
                    $accountReceivable->ageingSchedule->delete();
                }
            }
        });
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }
}
