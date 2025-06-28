<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgeingSchedule extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'ageing_schedules';
    protected $fillable = [
        'account_payable_id',
        'invoice_date',
        'due_date',
        'days_outstanding',
        'bucket' // 'Current','31–60','61–90','>90'
    ];

    public function accountPayable()
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id')->withDefault();
    }
}
