<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnProduct extends Model
{
    use SoftDeletes;
    protected $table = 'return_products';
    protected $fillable = [
        'return_number',
        'from_model_id',
        'from_model_type',
        'warehouse_id',
        'status',
        'reason'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function returnProductItem()
    {
        return $this->hasMany(ReturnProductItem::class, 'return_product_id');
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id');
    }
}
