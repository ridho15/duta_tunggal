<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashFlowItem extends Model
{
    protected $table = 'report_cash_flow_items';

    protected $fillable = [
        'section_id',
        'key',
        'label',
        'type',
        'resolver',
        'include_assets',
        'sort_order',
    ];

    protected $casts = [
        'include_assets' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(CashFlowSection::class, 'section_id');
    }

    public function prefixes(): HasMany
    {
        return $this->hasMany(CashFlowItemPrefix::class, 'item_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CashFlowItemSource::class, 'item_id');
    }
}
