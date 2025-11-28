<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class CashFlowCashAccount extends Model
{
    protected $table = 'report_cash_flow_cash_accounts';

    protected $fillable = [
        'prefix',
        'label',
        'sort_order',
    ];
}
