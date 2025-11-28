<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankAccount extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'cash_bank_accounts';

    protected $fillable = [
        'name',
        'bank_name',
        'account_number',
        'coa_id',
        'notes',
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}
