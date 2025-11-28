<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppOverheadItemPrefix extends Model
{
    protected $table = 'report_hpp_overhead_item_prefixes';

    protected $fillable = [
        'overhead_item_id',
        'prefix',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(HppOverheadItem::class, 'overhead_item_id');
    }
}
