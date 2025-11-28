<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankTransfer extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $fillable = [
        'number', 
        'date', 
        'from_coa_id', 
        'to_coa_id', 
        'amount', 
        'other_costs', 
        'other_costs_coa_id',
        'description', 
        'reference', 
        'attachment_path', 
        'status'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'other_costs' => 'decimal:2',
    ];

    public function fromCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'from_coa_id');
    }

    public function toCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'to_coa_id');
    }

    public function otherCostsCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'other_costs_coa_id');
    }
}
