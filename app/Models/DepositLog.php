<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepositLog extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'deposit_logs';
    protected $fillable = [
        'deposit_id',
        'type', // create, use, return, cancel, 'add
        'reference_type',
        'reference_id',
        'amount',
        'note',
        'created_by'
    ];

    public function deposit()
    {
        return $this->belongsTo(Deposit::class, 'deposit_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function reference()
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id')->withDefault();
    }
}
