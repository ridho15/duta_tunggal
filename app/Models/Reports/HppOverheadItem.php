<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HppOverheadItem extends Model
{
    protected $table = 'report_hpp_overhead_items';

    protected $fillable = [
        'key',
        'label',
        'sort_order',
    ];

    public function prefixes(): HasMany
    {
        return $this->hasMany(HppOverheadItemPrefix::class, 'overhead_item_id');
    }
}
