<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deposit extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity;
    protected $table = 'deposits';
    protected $fillable = [
        'from_model_type',
        'from_model_id',
        'amount',
        'used_amount',
        'remaining_amount',
        'coa_id',
        'payment_coa_id',
        'note',
        'status', //active,closed
        'created_by',
        'deposit_number'
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function paymentCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_coa_id')->withDefault();
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function depositLog()
    {
        return $this->hasMany(DepositLog::class, 'deposit_id');
    }

    public function depositLogRef()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }

    public function journalEntry()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    protected static function booted()
    {
        static::deleting(function ($deposit) {
            if ($deposit->isForceDeleting()) {
                $deposit->depositLog()->forceDelete();
            } else {
                $deposit->depositLog()->delete();
            }
        });

        static::restoring(function ($deposit) {
            $deposit->depositLog()->withTrashed()->restore();
        });
    }
}
