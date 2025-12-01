<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeStatementItem extends Model
{
    protected $fillable = [
        'account_name',
        'debit',
        'credit',
        'balance',
    ];

    public $timestamps = false;
}
