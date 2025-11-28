<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowItemPrefix extends Model
{
    protected $table = 'report_cash_flow_item_prefixes';

    protected $fillable = [
        'item_id',
        'prefix',
        'is_asset',
    ];

    protected $casts = [
        'is_asset' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CashFlowItem::class, 'item_id');
    }
}
