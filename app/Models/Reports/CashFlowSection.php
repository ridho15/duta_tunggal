<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashFlowSection extends Model
{
    protected $table = 'report_cash_flow_sections';

    protected $fillable = [
        'key',
        'label',
        'sort_order',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CashFlowItem::class, 'section_id');
    }
}
