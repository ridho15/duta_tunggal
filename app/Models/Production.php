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
        'production_date',
        'status' // draft, finished
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function qualityControl()
    {
        return $this->morphOne(QualityControl::class, 'from_model')->withDefault();
    }
}
