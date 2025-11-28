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
        'cabang_id',
        'department_id',
        'project_id',
        'source_type',
        'source_id',
        'transaction_id',
        // Bank reconciliation fields
        'bank_recon_id', // reference to reconciliation batch
        'bank_recon_status', // null|matched|cleared
        'bank_recon_date',
    ];

    protected $casts = [
        'date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(\App\Models\Cabang::class, 'cabang_id')->withDefault();
    }
}
