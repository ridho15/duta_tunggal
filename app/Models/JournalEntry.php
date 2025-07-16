<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'journal_entries';
    protected $fillable = [
        'coa_id',
        'date',
        'reference',
        'description',
        'debit',
        'credit',
        'journal_type', // misal: 'sales', 'purchase', 'manual'
        'source_type',
        'source_id'
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id')->withDefault();
    }
}
