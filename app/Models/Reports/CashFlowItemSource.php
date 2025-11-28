<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowItemSource extends Model
{
    protected $table = 'report_cash_flow_item_sources';

    protected $fillable = [
        'item_id',
        'label',
        'sort_order',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CashFlowItem::class, 'item_id');
    }
}
