<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class HppPrefix extends Model
{
    protected $table = 'report_hpp_prefixes';

    protected $fillable = [
        'category',
        'prefix',
        'sort_order',
    ];
}
