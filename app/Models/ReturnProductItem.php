<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnProductItem extends Model
{
    use SoftDeletes;
    protected $table = 'return_product_items';
    protected $fillable = [
        'return_product_id',
        'from_item_model_id',
        'from_item_model_type',
        'product_id',
        'quantity',
        'rak_id',
        'condition',
        'note'
    ];

    public function returnProduct()
    {
        return $this->belongsTo(ReturnProduct::class, 'return_product_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function fromItemModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_item_model_type', 'from_item_model_id');
    }
}
