<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'chart_of_accounts';
    protected $fillable = [
        'code',
        'name',
        'type', //'Asset', 'Liability', 'Equity', 'Revenue', 'Expense',
        'level',
        'parent_id',
        'is_active',
        'description'
    ];

    public function coaParent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id')->withDefault();
    }

    public function journalEntry()
    {
        return $this->hasMany(JournalEntry::class, 'coa_id');
    }
}
