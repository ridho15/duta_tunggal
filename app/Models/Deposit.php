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
        'note',
        'status', //active,closed
        'created_by'
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
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
}
