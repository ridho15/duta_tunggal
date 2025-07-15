<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxSetting extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'tax_settings';
    protected $fillable = [
        'name',
        'rate',
        'effective_date',
        'status',
        'type', //PPN, PPH, Custom
    ];
}
