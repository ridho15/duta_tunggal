<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetDepreciation extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity, CascadesJournalEntries;

    protected $fillable = [
        'asset_id',
        'depreciation_date',
        'period_month',
        'period_year',
        'amount',
        'accumulated_total',
        'book_value',
        'journal_entry_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'amount' => 'decimal:2',
        'accumulated_total' => 'decimal:2',
        'book_value' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}