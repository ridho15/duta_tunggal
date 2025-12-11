<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'productions';
    protected $fillable = [
        'production_number',
        'manufacturing_order_id',
        'quantity_produced',
        'production_date',
        'status' // draft, finished
    ];

    protected $casts = [
        'production_date' => 'date',
        'quantity_produced' => 'decimal:2',
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function qualityControl()
    {
        return $this->morphOne(QualityControl::class, 'from_model')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($production) {
            // Cascade delete related quality control
            $production->qualityControl()->delete();

            // Cascade delete related journal entries
            $production->journalEntries()->delete();
        });
    }
}
